<?php

namespace App\Jobs;

use App\Domain\Evaluation\EvaluationService;
use App\Models\CommercialCase;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshCaseDecision implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 55;

    public int $uniqueFor = 120;

    public function __construct(public int $caseId) {}

    public function uniqueId(): string
    {
        return (string) $this->caseId;
    }

    public function handle(EvaluationService $service): void
    {
        if ($case = CommercialCase::find($this->caseId)) {
            // Events queued together share the newest committed context, never a partial snapshot.
            $evaluation = $service->request($case);
            $service->run($evaluation->id);
        }
    }
}
