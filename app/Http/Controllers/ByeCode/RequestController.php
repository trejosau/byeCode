<?php

namespace App\Http\Controllers\ByeCode;

use App\Domain\Cases\CustomerJourney;
use App\Domain\Cases\Onboarding;
use App\Models\CommercialCase;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RequestController
{
    public function form(Request $request): Response
    {
        return Inertia::render('journey/request', ['selection' => $request->session()->get('selection', []),
            'variants' => Product::where('published', true)->whereHasStylePublished()->get(['id', 'sku', 'name', 'fit', 'color', 'size', 'moq']), 'physical' => $request->user()->role === 'commerce']);
    }

    public function store(Request $request, CustomerJourney $journey): RedirectResponse
    {
        $physical = $request->user()->role === 'commerce';
        $data = $request->validate(['submission_id' => 'required|uuid', 'company' => 'nullable|string|max:180', 'message' => 'nullable|string|max:8000',
            'desired_date' => 'nullable|date_format:Y-m-d|after_or_equal:today', 'lines' => 'required|array|min:1|max:30',
            'lines.*.product_id' => 'required|integer|distinct', 'lines.*.quantity' => 'required|integer|min:1|max:1000000',
            'name' => ($physical ? 'required' : 'nullable').'|string|max:180', 'email' => ($physical ? 'required' : 'nullable').'|email|max:180']);
        $case = DB::transaction(function () use ($request, $data, $physical, $journey) {
            $buyer = $physical ? app(Onboarding::class)->create($data['name'], $data['email'], 'customer') : $request->user();

            return $journey->submit($buyer, $data, $physical ? $request->user() : null);
        });
        if (! $physical) {
            if ($sessionKey = $request->session()->get('analytics_session')) {
                DB::table('activity_events')->where('session_key', $sessionKey)->whereNull('account_id')->whereNull('customer_id')
                    ->update(['account_id' => $request->user()->id, 'customer_id' => $case->customer_id]);
            }
            $request->session()->forget('selection');
        }

        return redirect($physical ? '/cases/'.$case->id.'/quote' : '/my/quotes/'.$case->public_id)->with('success', 'Solicitud recibida. El equipo preparará tu cotización.');
    }

    public function index(Request $request): Response
    {
        $query = CommercialCase::whereIn('data_set_id', DB::table('data_sets')->where('mode', 'live')->select('id'));
        if ($request->user()->role === 'customer') {
            $query->whereIn('customer_id', Customer::where('account_id', $request->user()->id)->select('id'));
        }

        return Inertia::render('journey/requests', ['cases' => $query->latest('updated_at')->limit(200)->get(['id', 'public_id', 'external_id', 'title', 'stage', 'status', 'amount_cents', 'assigned_to', 'created_at', 'updated_at']),
            'team' => $request->user()->role === 'commerce' ? User::where('role', 'commerce')->get(['id', 'name']) : [], 'commerce' => $request->user()->role === 'commerce']);
    }

    public function assign(Request $request, CommercialCase $case, CustomerJourney $journey): RedirectResponse
    {
        $journey->authorize($request->user(), $case);
        $data = $request->validate(['assigned_to' => 'nullable|integer']);
        if ($data['assigned_to']) {
            User::where('role', 'commerce')->findOrFail($data['assigned_to']);
        }
        $case->update($data);
        $journey->record($case, $request->user(), 'case.assigned', $data);

        return back();
    }
}
