<?php

namespace App\Domain\Events;

use App\Domain\Cases\CaseProjector;
use App\Domain\Demo\DemoContext;
use App\Domain\Evaluation\DecisionUpdates;
use App\Domain\Evaluation\EvaluationService;
use App\Domain\Support\Utc;
use App\Models\CaseEvent;
use App\Models\CommercialCase;
use App\Models\DataSet;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ImportService
{
    public function __construct(private EventProfile $profile, private CaseProjector $projector) {}

    public function import(DataSet $set, string $contents, string $name, string $profile = EventProfile::OFFICIAL, ?string $demoRunId = null): ImportBatch
    {
        if (strlen($contents) > config('byecode.max_import_bytes') || ! mb_check_encoding($contents, 'UTF-8')) {
            throw new InvalidArgumentException('Archivo demasiado grande o no codificado en UTF-8.');
        }
        $lines = preg_split('/\r\n|\r|\n/', ltrim($contents, "\xEF\xBB\xBF")) ?: [];
        if (count($lines) > config('byecode.max_import_lines') + 1) {
            throw new InvalidArgumentException('El archivo excede el máximo de líneas.');
        }
        $path = 'imports/'.$set->id.'/'.Str::uuid().'.jsonl';
        if (! Storage::disk('local')->put($path, $contents)) {
            throw new \RuntimeException('No se pudo preservar el archivo original.');
        }

        return DB::transaction(function () use ($set, $contents, $name, $profile, $lines, $path, $demoRunId) {
            $set = DataSet::whereKey($set->id)->lockForUpdate()->firstOrFail();
            if (app(DemoContext::class)->loading) {
                app(DemoContext::class)->files[] = $path;
            }
            $batch = ImportBatch::create(['data_set_id' => $set->id, 'name' => mb_substr($name, 0, 200), 'profile' => $profile, 'sha256' => hash('sha256', $contents), 'path' => $path, 'errors' => []]);
            $accepted = 0;
            $duplicates = 0;
            $errors = [];
            $changed = [];
            foreach ($lines as $offset => $line) {
                if (trim($line) === '') {
                    continue;
                }
                $raw = null;
                try {
                    $raw = json_decode($line, true, 32, JSON_THROW_ON_ERROR);
                    if (! is_array($raw) || array_is_list($raw)) {
                        throw new InvalidArgumentException('Se esperaba un objeto de evento.');
                    }
                    $normalized = $this->profile->normalize($raw, $profile);
                    $hash = EventProfile::hash($raw);
                    $existing = CaseEvent::where('data_set_id', $set->id)->where('event_id', $raw['event_id'])->first();
                    if ($existing) {
                        if ($existing->payload_hash !== $hash) {
                            throw new InvalidArgumentException('ID conflictivo: el evento ya existe con otro contenido.');
                        }
                        $duplicates++;

                        continue;
                    }
                    $case = CommercialCase::where('data_set_id', $set->id)->where('external_id', $raw['case_id'])->first();
                    if (! $case) {
                        if ($raw['event_type'] !== 'case.opened') {
                            throw new InvalidArgumentException('El caso debe abrirse antes en la secuencia de ingesta.');
                        }
                        $case = CommercialCase::create(['data_set_id' => $set->id, 'external_id' => $raw['case_id'], 'title' => $raw['case_id'], 'demo_run_id' => $demoRunId]);
                    } elseif ($raw['event_type'] === 'case.opened') {
                        throw new InvalidArgumentException('El caso ya tiene una apertura; use otro conjunto para una ejecución independiente.');
                    }
                    CaseEvent::create([
                        'data_set_id' => $set->id, 'commercial_case_id' => $case->id, 'import_batch_id' => $batch->id,
                        'event_id' => $raw['event_id'], 'event_type' => $raw['event_type'], 'occurred_at' => Utc::parse($raw['occurred_at']),
                        'ingested_at' => now(), 'line_number' => $offset + 1, 'profile' => $profile, 'payload_hash' => $hash,
                        'original' => $raw, 'data' => $normalized['data'],
                    ]);
                    $case->increment('revision');
                    $changed[$case->id] = true;
                    $accepted++;
                } catch (\JsonException|InvalidArgumentException $e) {
                    $caseId = is_array($raw) && is_string($raw['case_id'] ?? null) ? $raw['case_id'] : null;
                    $errors[] = ['line' => $offset + 1, 'case_id' => $caseId, 'message' => $e->getMessage()];
                    $affected = $caseId ? CommercialCase::where('data_set_id', $set->id)->where('external_id', $caseId)->first() : null;
                    if ($affected) {
                        $affected->update(['integrity_blocked' => true]);
                        $affected->increment('revision');
                        $changed[$affected->id] = true;
                    } else {
                        $set->update(['integrity_blocked' => true]);
                    }
                    // A conflicting ID also invalidates the case that owns the original.
                    if (is_array($raw) && is_string($raw['event_id'] ?? null)) {
                        $owner = CaseEvent::where('data_set_id', $set->id)->where('event_id', $raw['event_id'])->first();
                        if ($owner) {
                            CommercialCase::whereKey($owner->commercial_case_id)->update(['integrity_blocked' => true]);
                            $changed[$owner->commercial_case_id] = true;
                        }
                    }
                }
            }
            foreach (array_keys($changed) as $id) {
                $case = CommercialCase::findOrFail($id);
                $snapshot = $this->projector->snapshot($case);
                $case->update(['status' => $snapshot['state']['status'], 'stage' => $snapshot['state']['stage'], 'amount_cents' => $snapshot['state']['amount_cents'], 'next_review_at' => null]);
                if ($set->mode === 'live' && ! app(DemoContext::class)->loading) {
                    app(EvaluationService::class)->request($case);
                    app(DecisionUpdates::class)->caseChanged($case, false);
                }
            }
            $batch->update(['accepted' => $accepted, 'duplicates' => $duplicates, 'errors' => $errors]);

            return $batch;
        }, 3);
    }
}
