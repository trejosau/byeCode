<?php

namespace App\Domain\Evaluation;

use App\Models\AiUsage;
use App\Models\Evaluation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class BudgetLedger
{
    public function reserve(?Evaluation $evaluation, int $inputUpperBound, ?int $profileRunId = null): AiUsage
    {
        $model = (string) config('byecode.model');
        $pricing = config('byecode.prices')[$model] ?? null;
        if (! is_array($pricing)) {
            throw new RuntimeException('model_not_priced');
        }
        $pricing['mxn_per_usd'] = (float) config('byecode.budget.mxn_per_usd');
        $pricing['safety_multiplier'] = (float) config('byecode.budget.safety_multiplier');
        $reservation = $this->cost($inputUpperBound, (int) config('byecode.max_output_tokens'), $pricing);

        return DB::transaction(function () use ($evaluation, $pricing, $reservation, $model, $profileRunId) {
            DB::table('ai_budget_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
            if ($evaluation ? AiUsage::where('evaluation_id', $evaluation->id)->exists() : AiUsage::where('profile_run_id', $profileRunId)->exists()) {
                throw new RuntimeException('attempt_already_reserved');
            }
            $status = $this->summary();
            if ((int) (config('byecode.budget.daily_mxn') * 1000000) < $status['daily_micromxn'] + $reservation
                || (int) (config('byecode.budget.weekly_mxn') * 1000000) < $status['weekly_micromxn'] + $reservation
                || $status['daily_calls'] >= config('byecode.budget.daily_calls')) {
                throw new RuntimeException('budget_exhausted');
            }

            return AiUsage::create(['evaluation_id' => $evaluation?->id, 'profile_run_id' => $profileRunId, 'model' => $model, 'status' => 'reserved', 'reserved_micromxn' => $reservation, 'pricing' => $pricing]);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $pricing
     */
    public function cost(int $input, int $output, array $pricing): int
    {
        // Prices are USD / million tokens; result is integer micro-MXN. Round upwards.
        return (int) ceil(($input * $pricing['input'] + $output * $pricing['output']) * $pricing['mxn_per_usd'] * $pricing['safety_multiplier']);
    }

    public function settle(AiUsage $usage, int $input, int $output, ?string $requestId): void
    {
        $usage->update(['status' => 'confirmed', 'input_tokens' => $input, 'output_tokens' => $output,
            'charged_micromxn' => $this->cost($input, $output, $usage->pricing), 'request_id' => $requestId]);
    }

    /**
     * @return array<string, int|float>
     */
    public function summary(): array
    {
        $daily = AiUsage::where('created_at', '>=', now()->subHours(24));
        $weekly = AiUsage::where('created_at', '>=', now()->subDays(7));

        return [
            'daily_micromxn' => (int) (clone $daily)->sum(DB::raw('COALESCE(charged_micromxn, reserved_micromxn)')),
            'weekly_micromxn' => (int) $weekly->sum(DB::raw('COALESCE(charged_micromxn, reserved_micromxn)')),
            'daily_calls' => $daily->count(), 'daily_limit_mxn' => (int) config('byecode.budget.daily_mxn'),
            'weekly_limit_mxn' => (int) config('byecode.budget.weekly_mxn'),
            'uncertain_calls' => AiUsage::whereIn('status', ['reserved', 'uncertain'])->count(),
        ];
    }
}
