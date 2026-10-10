<?php

namespace App\Http\Controllers\ByeCode;

use App\Domain\Cases\CustomerJourney;
use App\Domain\Evaluation\DecisionUpdates;
use App\Models\CommercialCase;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TelemetryController
{
    public const TYPES = ['catalog.search', 'catalog.filter', 'catalog.product_opened', 'catalog.variant_selected', 'catalog.quantity_changed',
        'ui.session_started', 'ui.section_visible', 'ui.section_visibility_duration', 'ui.scroll_depth_reached', 'ui.section_expanded',
        'ui.fit_details_opened', 'ui.fabric_details_opened', 'ui.version_comparison_opened', 'ui.change_request_started',
        'ui.response_cta_clicked', 'proposal.viewed', 'proposal.version_viewed', 'proposal.print_clicked',
        'ux.repeated_click', 'ux.form_validation_failed', 'ux.api_error', 'ux.loading_timeout'];

    public function store(Request $request): Response
    {
        $data = $request->validate(['events' => 'required|array|max:50', 'events.*.event_id' => 'required|uuid',
            'events.*.type' => ['required', Rule::in(self::TYPES)], 'events.*.case_id' => 'nullable|integer',
            'events.*.data' => 'present|array:section,product_id,quantity,version,duration_ms,depth,query,filter,element',
            'events.*.data.section' => 'nullable|string|max:80', 'events.*.data.element' => 'nullable|string|max:80',
            'events.*.data.query' => 'nullable|string|max:120', 'events.*.data.filter' => 'nullable|string|max:80',
            'events.*.data.product_id' => 'nullable|integer|exists:products,id', 'events.*.data.quantity' => 'nullable|integer|min:0|max:1000000',
            'events.*.data.version' => 'nullable|integer|min:1', 'events.*.data.duration_ms' => 'nullable|integer|min:0|max:30000', 'events.*.data.depth' => 'nullable|integer|min:0|max:100']);
        if ($request->user() && $request->user()->role !== 'customer') {
            return response()->noContent();
        }
        $session = $request->session()->get('analytics_session');
        if (! $session) {
            $session = (string) Str::uuid();
            $request->session()->put('analytics_session', $session);
        }
        $customer = $request->user() ? Customer::where('account_id', $request->user()->id)->first() : null;
        DB::transaction(function () use ($data, $request, $session, $customer) {
            $changed = 0;
            $cases = [];
            foreach ($data['events'] as $event) {
                $case = null;
                if (! empty($event['case_id'])) {
                    abort_unless($request->user() !== null, 401);
                    $case = CommercialCase::whereKey($event['case_id'])->firstOrFail();
                    app(CustomerJourney::class)->authorize($request->user(), $case);
                    if (isset($event['data']['version'])) {
                        abort_unless(DB::table('quote_versions')->where('commercial_case_id', $case->id)->where('version', $event['data']['version'])->exists(), 422);
                    }
                }
                $inserted = DB::table('activity_events')->insertOrIgnore(['event_id' => $event['event_id'], 'customer_id' => $customer?->id, 'account_id' => $request->user()?->id,
                    'commercial_case_id' => $case?->id, 'session_key' => $session, 'type' => $event['type'], 'actor' => $request->user() ? 'customer' : 'visitor',
                    'source' => 'browser_unverified', 'data' => json_encode($event['data'], JSON_THROW_ON_ERROR), 'occurred_at' => now(), 'ingested_at' => now()]);
                $changed += $inserted;
                if ($inserted && $case) {
                    $cases[$case->id] = $case;
                }
            }
            if ($customer) {
                $changed += DB::table('activity_events')->where('session_key', $session)->whereNull('account_id')->update(['account_id' => $request->user()->id, 'customer_id' => $customer->id]);
                $changed += DB::table('activity_events')->where('account_id', $request->user()->id)->whereNull('customer_id')->update(['customer_id' => $customer->id]);
                if ($changed) {
                    app(DecisionUpdates::class)->customerChanged($customer->id);
                    foreach (CommercialCase::where('customer_id', $customer->id)->where('status', 'open')->get() as $openCase) {
                        $cases[$openCase->id] = $openCase;
                    }
                }
            }
            foreach ($cases as $changedCase) {
                app(DecisionUpdates::class)->caseChanged($changedCase);
            }
        });

        return response()->noContent();
    }
}
