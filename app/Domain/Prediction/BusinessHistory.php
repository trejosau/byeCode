<?php

namespace App\Domain\Prediction;

use App\Models\CommercialCase;
use App\Models\PredictionRun;
use App\Models\QuoteVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BusinessHistory
{
    /** @return list<string> */
    public function names(): array
    {
        return ['log_amount', 'amount_missing', 'log_messages', 'log_views', 'log_versions', 'log_units', 'log_variants', 'log_visible_ms',
            'log_changes', 'log_acceptances', 'log_rejections', 'age_days', 'prior_customer_cases', 'prior_paid_amount', 'business_mature_cases', 'business_acceptance_rate',
            ...array_map(fn ($n) => 'profile_bucket_'.$n, range(0, 15))];
    }

    /** @return list<float> */
    public function features(CommercialCase $case, CarbonImmutable $at): array
    {
        $events = DB::table('activity_events')->where('customer_id', $case->customer_id)->where('occurred_at', '<=', $at)->where('ingested_at', '<=', $at)->get();
        $caseEvents = $events->where('commercial_case_id', $case->id);
        $quote = QuoteVersion::where('commercial_case_id', $case->id)->where('created_at', '<=', $at)->latest('version')->first();
        $lines = $quote ? $quote->lines : [];
        $counts = $caseEvents->countBy('type');
        $visible = $caseEvents->where('type', 'ui.section_visibility_duration')->sum(fn ($e) => json_decode($e->data, true)['duration_ms'] ?? 0);
        $priorCases = DB::table('commercial_cases')->where('customer_id', $case->customer_id)->where('created_at', '<', $at)->where('id', '!=', $case->id)->pluck('id');
        $paid = DB::table('sale_records')->whereIn('commercial_case_id', $priorCases)->where('kind', 'payment')->where('created_at', '<=', $at)->where('occurred_at', '<=', $at)->sum('amount_cents');
        $closed = DB::table('case_events')->join('data_sets', 'data_sets.id', '=', 'case_events.data_set_id')->where('data_sets.mode', 'live')->where('event_type', 'case.closed')->where('occurred_at', '<', $at)->where('ingested_at', '<', $at)->get(['case_events.data']);
        $won = $closed->filter(fn ($e) => json_decode($e->data, true)['status'] === 'accepted')->count();
        $buckets = array_fill(0, 16, 0.0);
        // Signed feature hashing admits new typed properties without schema mutation.
        // Every value was known at the historical cutoff; no current profile backfill.
        $facts = DB::table('profile_facts')->where('customer_id', $case->customer_id)->where('status', '!=', 'conflicting')->where('created_at', '<=', $at)->where('observed_at', '<=', $at)
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', $at))->orderByDesc('id')->get()->unique(fn ($f) => $f->name.'|'.$f->scope);
        foreach ($facts as $fact) {
            $hash = hash('sha256', $fact->name.'|'.$fact->scope.'|'.$fact->value.'|'.$fact->nature);
            $index = hexdec(substr($hash, 0, 2)) % 16;
            $buckets[$index] += hexdec(substr($hash, 2, 2)) % 2 ? 1.0 : -1.0;
        }

        return [log1p(($quote ? $quote->total_cents : 0) / 100), $quote ? 0.0 : 1.0, log1p($counts['message.received'] ?? 0), log1p($counts['proposal.version_viewed'] ?? 0),
            log1p(QuoteVersion::where('commercial_case_id', $case->id)->where('created_at', '<=', $at)->count()), log1p(array_sum(array_column($lines, 'quantity'))), log1p(count($lines)), log1p($visible),
            log1p($counts['buyer.change'] ?? 0), log1p($counts['buyer.accept'] ?? 0), log1p($counts['buyer.reject'] ?? 0),
            (float) max(0, min(365, CarbonImmutable::parse($case->created_at)->diffInSeconds($at) / 86400)), (float) $priorCases->count(), log1p($paid / 100),
            log1p($closed->count()), $closed->count() ? $won / $closed->count() : 0.0, ...$buckets];
    }

    public function train(User $user): PredictionRun
    {
        $rows = [];
        $seen = 0;
        $censored = 0;
        $cases = CommercialCase::whereNotNull('customer_id')->whereIn('data_set_id', DB::table('data_sets')->where('mode', 'live')->select('id'))->orderBy('created_at')->get();
        foreach ($cases as $case) {
            $seen++;
            $at = CarbonImmutable::parse($case->created_at)->addDay();
            $horizon = $at->addDays(14);
            if ($horizon->isFuture()) {
                $censored++;

                continue;
            }
            $closures = DB::table('case_events')->where('commercial_case_id', $case->id)->where('event_type', 'case.closed')->orderBy('ingested_at')->get();
            if ($closures->contains(fn ($e) => CarbonImmutable::parse($e->occurred_at)->lessThanOrEqualTo($at) && CarbonImmutable::parse($e->ingested_at)->lessThanOrEqualTo($at))) {
                continue;
            }
            $win = $closures->first(fn ($e) => json_decode($e->data, true)['status'] === 'accepted' && CarbonImmutable::parse($e->occurred_at)->greaterThan($at) && CarbonImmutable::parse($e->occurred_at)->lessThanOrEqualTo($horizon));
            $known = $win ? $horizon->max(CarbonImmutable::parse($win->ingested_at)) : $horizon;
            $rows[] = ['case_id' => (string) $case->id, 'group' => (string) $case->customer_id, 'at' => $at->toIso8601ZuluString(),
                'label' => $win ? 1 : 0, 'label_known_at' => $known->toIso8601ZuluString(), 'x' => $this->features($case, $at)];
        }

        return app(PredictionService::class)->trainRows($user, $rows, $seen, $censored, 'real', json_encode($rows, JSON_THROW_ON_ERROR), $this->names(), 'business-history-v1');
    }
}
