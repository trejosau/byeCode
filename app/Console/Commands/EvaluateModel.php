<?php

namespace App\Console\Commands;

use App\Domain\Evaluation\BudgetLedger;
use App\Domain\Evaluation\EvaluationService;
use App\Domain\Events\ImportService;
use App\Models\CommercialCase;
use App\Models\DataSet;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class EvaluateModel extends Command
{
    protected $signature = 'byecode:evaluate-model {--user= : ID de la cuenta propietaria} {--allow-paid : Autoriza llamadas reales con cargo al presupuesto} {--repetitions=1 : Entre 1 y 3 repeticiones}';

    protected $description = 'Evalúa escenarios semánticos versionados, con métricas separadas de mocks y reglas';

    public function handle(ImportService $importer, EvaluationService $service, BudgetLedger $budget): int
    {
        if (! $this->option('allow-paid') || ! config('integrations.openai.api_key')) {
            $this->error('No se realizaron llamadas. Configura OPENAI_API_KEY y añade --allow-paid para autorizar la evaluación real.');

            return self::FAILURE;
        }
        $id = $this->option('user');
        $count = filter_var($this->option('repetitions'), FILTER_VALIDATE_INT);
        $user = User::whereKey($id)->first();
        if (! $user || $count === false || $count < 1 || $count > 3) {
            $this->error('Indica un --user existente y entre 1 y 3 repeticiones.');

            return self::FAILURE;
        }
        $contents = file_get_contents(base_path('tests/Fixtures/semantic-v1.json'));
        if ($contents === false) {
            $this->error('No se pudo leer la batería de escenarios.');

            return self::FAILURE;
        }
        $fixtures = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $before = $budget->summary();
        $rows = [];
        for ($repeat = 1; $repeat <= $count; $repeat++) {
            $set = DataSet::create(['user_id' => $user->id, 'name' => 'Ensayo semántico v1 · '.now()->format('Y-m-d H:i:s').' · '.$repeat, 'mode' => 'kit', 'evaluation_at' => config('byecode.kit_clock')]);
            foreach ($fixtures as $fixture) {
                $id = $fixture['id'];
                $events = [['event_id' => $id.'-open', 'case_id' => $id, 'event_type' => 'case.opened', 'occurred_at' => '2026-10-09T10:00:00Z', 'data' => ['status' => 'open', 'proposal_amount_mxn' => 10000]]];
                if ($fixture['message'] !== null) {
                    $events[] = ['event_id' => $id.'-message', 'case_id' => $id, 'event_type' => 'message.received', 'occurred_at' => '2026-10-09T15:00:00Z', 'data' => ['message' => $fixture['message']]];
                }
                foreach ($fixture['extra'] as $i => $event) {
                    $events[] = ['event_id' => $id.'-extra-'.$i, 'case_id' => $id, ...$event];
                }
                $batch = $importer->import($set, implode("\n", array_map(fn ($e) => json_encode($e, JSON_THROW_ON_ERROR), $events)), 'Ensayo '.$id);
                if ($batch->errors) {
                    $this->error('Fixture inválida: '.$id);

                    return self::FAILURE;
                }
                $case = CommercialCase::where('data_set_id', $set->id)->where('external_id', $id)->firstOrFail();
                $evaluation = $service->request($case);
                $service->run($evaluation->id);
                $evaluation->refresh();
                $r = $service->current($case);
                $kinds = $r ? array_column($r->signals, 'kind') : [];
                $actionPass = $r && in_array($r->result['action'], $fixture['expected_actions'], true);
                $signalPass = $r && ($fixture['expected_kinds'] === [] || array_intersect($fixture['expected_kinds'], $kinds) !== []);
                $rows[] = ['scenario' => $id, 'repeat' => $repeat, 'evaluation_id' => $evaluation->id, 'source' => $r?->source, 'status' => $evaluation->status, 'error_code' => $evaluation->error_code, 'action' => $r?->result['action'], 'action_pass' => (bool) $actionPass, 'signal_pass' => (bool) $signalPass, 'passed' => (bool) ($actionPass && $signalPass)];
            }
        }
        $completed = count(array_filter($rows, fn ($r) => $r['status'] === 'completed'));
        $passed = count(array_filter($rows, fn ($r) => $r['passed']));
        $report = ['suite' => 'semantic-v1', 'generated_at' => now()->toIso8601String(), 'model' => config('byecode.model'), 'prompt_version' => config('byecode.prompt_version'), 'rules_version' => config('byecode.rules_version'),
            'total' => count($rows), 'coverage' => $completed / count($rows), 'passed' => $passed, 'pass_rate_over_all' => $passed / count($rows), 'provider_results' => count(array_filter($rows, fn ($r) => $r['source'] === 'openai')),
            'cost_delta_micromxn' => $budget->summary()['weekly_micromxn'] - $before['weekly_micromxn'], 'rows' => $rows,
            'limitations' => ['Prueba de regresión pequeña; no demuestra precisión comercial ni probabilidad de cierre.', 'Se contabilizan fallos y abstenciones; casos resueltos por reglas no acreditan calidad de GPT.', 'Revisar manualmente citas y sentidos de negaciones, condiciones y conflictos antes de usar en producción.']];
        $path = 'evaluations/semantic-v1-'.now()->format('Ymd-His').'.json';
        if (! Storage::disk('local')->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))) {
            $this->error('No se pudo guardar el reporte.');

            return self::FAILURE;
        }
        $this->info('Reporte privado: '.Storage::disk('local')->path($path));
        $this->info($passed.'/'.count($rows).' escenarios aprobados; cobertura '.$completed.'/'.count($rows).'.');

        return $passed === count($rows) ? self::SUCCESS : self::FAILURE;
    }
}
