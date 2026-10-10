<?php

namespace App\Http\Controllers\ByeCode;

use App\Domain\Cases\CommercialProfile;
use App\Domain\Cases\CustomerProfile;
use App\Jobs\EnrichCustomer;
use App\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController
{
    public function show(Request $request, Customer $customer, CustomerProfile $profile, CommercialProfile $commercial): Response
    {
        $view = $request->validate(['tab' => ['nullable', Rule::in(['profile', 'activity', 'opportunities'])], 'event' => 'nullable|integer|min:1', 'directory' => 'nullable|string|max:2000']);
        parse_str($view['directory'] ?? '', $directory);
        $directory = array_filter(array_intersect_key($directory, ['search' => true, 'sort' => true, 'page' => true, ...$commercial->dimensions()]), 'is_string');
        $selectedEvent = empty($view['event']) ? null : DB::table('activity_events')->where('customer_id', $customer->id)->where('id', $view['event'])->first();
        abort_if(! empty($view['event']) && ! $selectedEvent, 404);
        $indicators = $profile->indicators($customer);
        $indicators['last_activity'] = $indicators['last_activity'] ? CarbonImmutable::parse($indicators['last_activity'], 'UTC')->toIso8601ZuluString() : null;

        return Inertia::render('journey/profile', ['customer' => $customer, 'indicators' => $indicators,
            'activeTab' => isset($view['event']) ? 'activity' : ($view['tab'] ?? 'profile'), 'directoryQuery' => http_build_query($directory),
            'selectedEvent' => $selectedEvent ? [...(array) $selectedEvent, 'data' => json_decode($selectedEvent->data, true), 'occurred_at' => CarbonImmutable::parse($selectedEvent->occurred_at, 'UTC')->toIso8601ZuluString()] : null,
            'commercialDimensions' => $commercial->dimensions(), 'commercialProfile' => $commercial->summaries([$customer->id])[$customer->id] ?? [],
            'facts' => DB::table('profile_facts')->where('customer_id', $customer->id)->latest('id')->limit(200)->get()->map(fn ($r) => [...(array) $r, 'value' => json_decode($r->value, true), 'evidence' => json_decode($r->evidence, true)]),
            'events' => DB::table('activity_events')->where('customer_id', $customer->id)->latest('id')->limit(200)->get()->map(fn ($r) => [...(array) $r, 'data' => json_decode($r->data, true), 'occurred_at' => CarbonImmutable::parse($r->occurred_at, 'UTC')->toIso8601ZuluString()]),
            'runs' => DB::table('profile_runs')->where('customer_id', $customer->id)->latest('id')->limit(20)->get(['id', 'cutoff', 'status', 'error', 'updated_at']),
            'cases' => DB::table('commercial_cases')->where('customer_id', $customer->id)->latest('id')->get(['id', 'title', 'public_id', 'status']),
            'readOnly' => $request->user()->role === 'admin', 'aiReady' => (bool) config('integrations.openai.api_key')]);
    }

    public function correct(Request $request, Customer $customer, CustomerProfile $profile): RedirectResponse
    {
        try {
            $profile->fact($customer, $request->all(), 'confirmed');
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'invalid_evidence') {
                throw $e;
            }

            return back()->withErrors(['evidence' => 'Selecciona evidencia existente de este cliente.']);
        }

        return back()->with('success', 'Corrección registrada con evidencia e historial.');
    }

    public function analyze(Customer $customer): RedirectResponse
    {
        EnrichCustomer::dispatch($customer->id, true);

        return back()->with('success', 'Análisis solicitado dentro del presupuesto compartido.');
    }
}
