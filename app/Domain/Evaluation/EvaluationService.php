<?php

namespace App\Domain\Evaluation;

use App\Domain\Cases\CaseProjector;
use App\Domain\Decision\ActionRanking;
use App\Domain\Decision\DecisionValidator;
use App\Domain\Decision\RuleEngine;
use App\Domain\Events\EventProfile;
use App\Domain\Support\Utc;
use App\Models\CommercialCase;
use App\Models\DataSet;
use App\Models\Evaluation;
use App\Models\Recommendation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

final class EvaluationService
{
    public function __construct(private CaseProjector $projector, private RuleEngine $rules, private DecisionValidator $validator, private OpenAiAdapter $provider) {}

    public function request(CommercialCase $case, bool $retry = false): Evaluation
    {
        return DB::transaction(function () use ($case, $retry) {
            $set = DataSet::whereKey($case->data_set_id)->lockForUpdate()->firstOrFail();
            $case = CommercialCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            $snapshot = $this->projector->snapshot($case);
            $provider = ! $case->demo_run_id && config('integrations.openai.api_key') ? 'openai' : 'rules';
            Evaluation::where('commercial_case_id', $case->id)->where('status', 'pending')
                ->where(fn ($q) => $q->where('case_revision', '!=', $case->revision)->orWhere('provider', '!=', $provider)
                    ->orWhere('prompt_version', '!=', config('byecode.prompt_version'))->orWhere('rules_version', '!=', config('byecode.rules_version')))
                ->update(['status' => 'stale', 'error_code' => 'stale_context', 'error_message' => 'Una revisión más reciente sustituyó este contexto antes de procesarlo.', 'finished_at' => now()]);
            $clockKey = $set->mode === 'kit' ? $snapshot['evaluation_at'] : Utc::iso(Utc::parse($snapshot['evaluation_at'])->startOfHour());
            $fingerprint = hash('sha256', json_encode([$case->id, $case->revision, $clockKey, $provider, config('byecode.model'), config('byecode.prompt_version'), config('byecode.rules_version'),
                $snapshot['state'], $this->rules->assess($snapshot), array_column($snapshot['events'], 'event_id'), $snapshot['quote']['is_expired'] ?? null], JSON_THROW_ON_ERROR));
            $active = Evaluation::where('commercial_case_id', $case->id)->where('case_revision', $case->revision)
                ->whereIn('status', ['pending', 'running'])->where('provider', $provider)
                ->where('prompt_version', config('byecode.prompt_version'))->where('rules_version', config('byecode.rules_version'))->latest('id')->first();
            if ($active) {
                return $active;
            }

            $evaluation = Evaluation::firstOrCreate(['fingerprint' => $fingerprint], [
                'commercial_case_id' => $case->id, 'case_revision' => $case->revision, 'status' => 'pending', 'provider' => $provider,
                'prompt_version' => config('byecode.prompt_version'), 'rules_version' => config('byecode.rules_version'),
                'evaluation_at' => Utc::parse($snapshot['evaluation_at']), 'snapshot' => $snapshot,
            ]);
            if ($retry && in_array($evaluation->status, ['failed', 'blocked', 'stale'], true)) {
                $attempts = Evaluation::where('commercial_case_id', $case->id)->where('case_revision', $case->revision)->where('created_at', '>=', now()->subHour())->count();
                if ($attempts >= 3) {
                    throw ValidationException::withMessages(['evaluation' => 'Máximo de tres intentos por revisión y hora; revisa el fallo antes de continuar.']);
                }
                $copy = $evaluation->replicate(['fingerprint', 'status', 'dispatched_at', 'started_at', 'finished_at', 'error_code', 'error_message', 'duration_ms']);
                $copy->fingerprint = hash('sha256', $fingerprint.Str::uuid());
                $copy->status = 'pending';
                $copy->snapshot = $snapshot;
                $copy->evaluation_at = Utc::parse($snapshot['evaluation_at']);
                $copy->save();

                return $copy;
            }

            return $evaluation;
        }, 3);
    }

    public function run(int $id): void
    {
        $claimed = Evaluation::whereKey($id)->where('status', 'pending')->update(['status' => 'running', 'started_at' => now()]);
        if (! $claimed) {
            return;
        }
        $evaluation = Evaluation::findOrFail($id);
        $start = hrtime(true);
        try {
            $s = $evaluation->snapshot;
            $case = CommercialCase::findOrFail($evaluation->commercial_case_id);
            if ($case->revision !== $evaluation->case_revision) {
                $this->fail($evaluation, 'stale_context', 'stale');

                return;
            }
            if ($s['integrity_blocked'] || $case->integrity_blocked || DataSet::findOrFail($case->data_set_id)->integrity_blocked) {
                $this->fail($evaluation, 'incomplete_history', 'blocked');

                return;
            }
            if ($case->demo_run_id && $evaluation->provider !== 'rules') {
                $evaluation->update(['provider' => 'rules']);
            }
            $decision = $this->rules->deterministic($s);
            $signals = [];
            $semantic = null;
            $source = 'rules';
            if ($decision === null && $evaluation->provider === 'rules') {
                $r = $this->rules->assess($s);
                if ($r['reevaluate_at'] || $r['contact_prohibited']) {
                    $decision = $this->rules->result($s, $r['reevaluate_at'] ? 'wait' : 'no_action',
                        $r['reevaluate_at'] ? 'at_date' : 'not_applicable', $r['reevaluate_at'],
                        'Contingencia: se conserva el bloqueo conocido. Los mensajes todavía necesitan interpretación de IA; no se autoriza contacto.',
                        $r['evidence']);
                    $decision['confidence'] = 'medium';
                    $decision['requires_human_review'] = true;
                    $decision['missing_information'] = [$case->demo_run_id ? 'Análisis semántico de demostración deshabilitado; sin llamadas facturables.' : 'Interpretación semántica pendiente: configure OPENAI_API_KEY.'];
                    $source = 'rules_contingency';
                } else {
                    $this->fail($evaluation, $case->demo_run_id ? 'demo_ai_disabled' : 'missing_api_key', 'blocked');

                    return;
                }
            } elseif ($decision === null) {
                $cached = $this->cachedSemantic($s);
                $compiled = $cached ?? $this->validator->compile($s, $this->provider->evaluate($evaluation));
                $decision = $compiled['result'];
                $signals = $compiled['signals'];
                $semantic = $compiled['candidate'];
                $source = $cached ? 'openai_cache' : 'openai';
                if ($cached) {
                    $evaluation->update(['model' => $cached['model']]);
                }
            }
            if ($decision === null) {
                throw new InvalidArgumentException('Sin candidata.');
            }
            $validation = $this->rules->assess($s, $signals);
            $this->validator->validate($s, $decision, $signals);
            $validUntil = Utc::parse($s['evaluation_at'])->addHours(24);
            if ($validation['reevaluate_at']) {
                $validUntil = $validUntil->min(Utc::parse($validation['reevaluate_at']));
            }
            if ($decision['scheduled_for']) {
                $validUntil = $validUntil->min(Utc::parse($decision['scheduled_for']));
            }
            if ($s['next_boundary_at']) {
                $validUntil = $validUntil->min(Utc::parse($s['next_boundary_at']));
            }
            if ($s['quote'] && ! $s['quote']['is_expired']) {
                $validUntil = $validUntil->min(Utc::parse($s['quote']['valid_until']));
            }
            DB::transaction(function () use ($evaluation, $decision, $signals, $semantic, $validation, $source, $validUntil, $start) {
                $initial = CommercialCase::findOrFail($evaluation->commercial_case_id);
                $set = DataSet::whereKey($initial->data_set_id)->lockForUpdate()->firstOrFail();
                $case = CommercialCase::whereKey($initial->id)->lockForUpdate()->firstOrFail();
                if ($case->revision !== $evaluation->case_revision || $case->integrity_blocked || $set->integrity_blocked
                    || $evaluation->rules_version !== config('byecode.rules_version')
                    || $validUntil->lessThanOrEqualTo($this->projector->clock($set))) {
                    $this->fail($evaluation, 'stale_context', 'stale');

                    return;
                }
                // Time boundaries may have changed during HTTP. Revalidate on a fresh snapshot.
                $fresh = $this->projector->snapshot($case);
                $this->validator->validate($fresh, $decision, $signals);
                $alternatives = app(ActionRanking::class)->build($fresh, $decision, $signals, $semantic);
                $validation['semantic_candidate'] = $semantic;
                $previous = Recommendation::where('commercial_case_id', $case->id)->latest('id')->first();
                if ($previous) {
                    $previousSnapshot = Evaluation::findOrFail($previous->evaluation_id)->snapshot;
                    $validation['change_summary'] = ['previous_action' => $previous->result['action'], 'previous_recommendation_id' => $previous->id,
                        'changed_fields' => array_values(array_filter(['action', 'timing', 'scheduled_for', 'confidence', 'risk_level', 'missing_information'], fn ($field) => $previous->result[$field] !== $decision[$field])),
                        'new_event_ids' => array_values(array_diff(array_column($fresh['events'], 'event_id'), array_column($previousSnapshot['events'], 'event_id'))),
                        'new_activity_count' => count(array_diff(array_column($fresh['activity_context']['events'] ?? [], 'event_id'), array_column($previousSnapshot['activity_context']['events'] ?? [], 'event_id')))];
                }
                Recommendation::firstOrCreate(['evaluation_id' => $evaluation->id], [
                    'commercial_case_id' => $case->id, 'case_revision' => $case->revision, 'source' => $source,
                    'publication_status' => $decision['requires_human_review'] ? 'awaiting_review' : 'published',
                    'result' => $decision, 'signals' => $signals, 'validation' => $validation, 'alternatives' => $alternatives,
                    'valid_until' => $validUntil, 'created_at' => now(),
                ]);
                $case->update(['next_review_at' => $set->mode === 'live' && $case->status === 'open' ? $validUntil : null]);
                $evaluation->update(['status' => 'completed', 'finished_at' => now(), 'duration_ms' => (int) ((hrtime(true) - $start) / 1000000)]);
            }, 3);
        } catch (Throwable $e) {
            $code = $e instanceof InvalidArgumentException ? 'invalid_candidate' : $e->getMessage();
            if (! preg_match('/^[a-z_0-9]+$/D', $code)) {
                $code = 'evaluation_failed';
            }
            $this->fail($evaluation, $code);
        }
    }

    public function current(CommercialCase $case): ?Recommendation
    {
        $rec = Recommendation::where('commercial_case_id', $case->id)->orderByDesc('id')->first();
        if (! $rec || ! $this->isCurrent($case, $rec)) {
            return null;
        }

        return $rec;
    }

    /** @param array<string, mixed> $snapshot
     * @return array<string, mixed>|null
     */
    private function cachedSemantic(array $snapshot): ?array
    {
        $rec = Recommendation::where('commercial_case_id', $snapshot['case_pk'])->whereIn('source', ['openai', 'openai_cache'])
            ->where('publication_status', '!=', 'rejected')->where('created_at', '>=', now()->subHour())->latest('id')->first();
        if (! $rec || $rec->valid_until->lessThanOrEqualTo(Utc::parse($snapshot['evaluation_at']))) {
            return null;
        }
        $previous = Evaluation::findOrFail($rec->evaluation_id);
        if ($previous->prompt_version !== config('byecode.prompt_version') || $previous->rules_version !== config('byecode.rules_version')
            || ! str_starts_with((string) $previous->model, (string) config('byecode.model'))) {
            return null;
        }
        $key = static fn (array $s) => EventProfile::hash([
            'events' => array_values(array_filter($s['events'], fn ($e) => $e['event_type'] !== 'proposal.viewed')),
            'state' => array_diff_key($s['state'], ['stage' => true]), 'quote' => $s['quote'], 'customer_context' => $s['customer_context'] ?? null, 'activity_context' => $s['activity_context'] ?? null,
        ]);
        if ($key($previous->snapshot) !== $key($snapshot)) {
            return null;
        }
        try {
            $this->validator->validate($snapshot, $rec->result, $rec->signals);
        } catch (InvalidArgumentException) {
            return null;
        }

        $candidate = $rec->validation['semantic_candidate'] ?? null;
        if (! is_array($candidate)) {
            return null;
        }

        return ['result' => $rec->result, 'signals' => $rec->signals, 'candidate' => $candidate, 'model' => $previous->model];
    }

    public function isCurrent(CommercialCase $case, Recommendation $rec): bool
    {
        $set = DataSet::findOrFail($case->data_set_id);
        $evaluation = Evaluation::findOrFail($rec->evaluation_id);

        return $this->isCurrentWith($case, $rec, $set, $evaluation);
    }

    public function isCurrentWith(CommercialCase $case, Recommendation $rec, DataSet $set, Evaluation $evaluation): bool
    {

        return $rec->publication_status !== 'rejected' && $rec->case_revision === $case->revision && ! $case->integrity_blocked && ! $set->integrity_blocked
            && $rec->valid_until->greaterThan($this->projector->clock($set))
            && $evaluation->rules_version === config('byecode.rules_version')
            && $evaluation->prompt_version === config('byecode.prompt_version');
    }

    private function fail(Evaluation $evaluation, string $code, string $status = 'failed'): void
    {
        $messages = [
            'demo_ai_disabled' => 'Escenario de demostración: interpretación semántica deshabilitada. Puedes continuar cotizando y registrar decisiones; no se simula una respuesta de IA.',
            'missing_api_key' => 'Falta OPENAI_API_KEY. El caso necesita interpretación; no se inventó una recomendación.',
            'incomplete_history' => 'Historial incompleto o conflictivo. Revise la importación y cargue un conjunto corregido.',
            'budget_exhausted' => 'Se alcanzó el límite preventivo de consumo. La IA está pausada hasta liberar presupuesto.',
            'context_too_large' => 'El historial excede el límite de contexto. Requiere revisión; no se recortaron mensajes decisivos.',
            'invalid_candidate' => 'La propuesta de IA no superó contrato, evidencia o reglas. Requiere revisión.',
            'stale_context' => 'El contexto o su vigencia cambió. La respuesta anterior no se publicó.',
            'worker_interrupted' => 'El trabajo se interrumpió. No se repetirá automáticamente una llamada de costo incierto.',
        ];
        $evaluation->update(['status' => $status, 'error_code' => $code, 'error_message' => $messages[$code] ?? 'No se obtuvo una respuesta válida del proveedor. Revise configuración y reintente explícitamente.', 'finished_at' => now()]);
    }
}
