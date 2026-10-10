<?php

namespace App\Jobs;

use App\Domain\Evaluation\EvaluationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateCase implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 55;

    public function __construct(public int $evaluationId) {}

    public function handle(EvaluationService $service): void
    {
        $service->run($this->evaluationId);
    }
}
