<?php

namespace App\Console\Commands;

use App\Domain\Evaluation\EvaluationService;
use App\Jobs\EvaluateCase;
use App\Models\CommercialCase;
use App\Models\DataSet;
use App\Models\Evaluation;
use Illuminate\Console\Command;

class DispatchEvaluations extends Command
{
    protected $signature = 'byecode:dispatch {--inline : Procesar en este proceso, sin esperar al worker}';

    protected $description = 'Recupera evaluaciones pendientes y agenda reevaluaciones; nunca envía contactos';

    public function handle(EvaluationService $service): int
    {
        Evaluation::where('status', 'running')->where('started_at', '<', now()->subMinutes(3))->update([
            'status' => 'failed', 'error_code' => 'worker_interrupted',
            'error_message' => 'Worker interrumpido. No se repite una llamada de costo incierto automáticamente.', 'finished_at' => now(),
        ]);
        $live = DataSet::where('mode', 'live')->pluck('id');
        foreach (CommercialCase::whereIn('data_set_id', $live)->where('next_review_at', '<=', now())->limit(100)->get() as $case) {
            $service->request($case);
            $case->update(['next_review_at' => null]);
        }
        $pending = Evaluation::where('status', 'pending')->where(function ($q) {
            $q->whereNull('dispatched_at')->orWhere('dispatched_at', '<', now()->subMinutes(2));
        })->orderBy('id')->limit(100)->get();
        foreach ($pending as $evaluation) {
            if ($this->option('inline')) {
                $service->run($evaluation->id);
            } else {
                // Stamp before dispatch: a process crash is recovered after the lease expires.
                $evaluation->update(['dispatched_at' => now()]);
                EvaluateCase::dispatch($evaluation->id);
            }
        }
        $this->info($pending->count().' evaluaciones atendidas.');

        return self::SUCCESS;
    }
}
