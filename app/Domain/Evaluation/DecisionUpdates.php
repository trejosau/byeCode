<?php

namespace App\Domain\Evaluation;

use App\Domain\Demo\DemoContext;
use App\Jobs\EnrichCustomer;
use App\Jobs\RefreshCaseDecision;
use App\Models\CommercialCase;
use App\Models\DataSet;
use Illuminate\Support\Facades\DB;

final class DecisionUpdates
{
    public function caseChanged(CommercialCase $case, bool $incrementRevision = true): void
    {
        if (DataSet::findOrFail($case->data_set_id)->mode !== 'live') {
            return;
        }
        if ($incrementRevision) {
            $case->increment('revision');
        }
        // Durable recovery marker; dispatch only after the entire business operation commits.
        $case->update(['next_review_at' => now()]);
        if (app(DemoContext::class)->loading) {
            return;
        }
        $id = $case->id;
        DB::afterCommit(static fn () => RefreshCaseDecision::dispatch($id));
    }

    public function customerChanged(int $customerId): void
    {
        if (app(DemoContext::class)->loading) {
            return;
        }
        if (config('integrations.openai.api_key')) {
            DB::afterCommit(static fn () => EnrichCustomer::dispatch($customerId));
        }
    }
}
