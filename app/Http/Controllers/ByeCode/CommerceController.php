<?php

namespace App\Http\Controllers\ByeCode;

use App\Domain\Cases\CaseProjector;
use App\Domain\Cases\CommercialProfile;
use App\Domain\Cases\CustomerDirectory;
use App\Domain\Cases\CustomerJourney;
use App\Domain\Cases\QuoteService;
use App\Domain\Cases\RecommendationReviewService;
use App\Domain\Cases\Workspace;
use App\Domain\Decision\RuleEngine;
use App\Domain\Demo\DemoPolicy;
use App\Domain\Evaluation\BudgetLedger;
use App\Domain\Evaluation\EvaluationService;
use App\Domain\Prediction\PredictionService;
use App\Domain\Support\Money;
use App\Domain\Support\Utc;
use App\Http\Controllers\Controller;
use App\Jobs\EvaluateCase;
use App\Models\CommercialCase;
use App\Models\Customer;
use App\Models\DataSet;
use App\Models\Evaluation;
use App\Models\HumanReview;
use App\Models\Product;
use App\Models\QuoteVersion;
use App\Models\Recommendation;
use App\Models\RecommendationFeedback;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CommerceController extends Controller
{
    public function __construct(private Workspace $workspace, private CaseProjector $projector, private EvaluationService $evaluations) {}

    public function dashboard(Request $request, BudgetLedger $budget): Response
    {
        $cases = $this->caseList($request);

        return Inertia::render('dashboard', [
            'cases' => $cases, 'budget' => $budget->summary(), 'aiReady' => (bool) config('integrations.openai.api_key'),
            'model' => config('byecode.model'), 'sets' => DataSet::where(fn ($q) => $q->where('mode', 'live')->orWhere('user_id', $request->user()->id))->get(['id', 'name', 'mode']),
        ]);
    }

    public function index(Request $request): Response
    {
        return Inertia::render('byecode/cases', ['cases' => $this->caseList($request), 'filters' => $request->only('search', 'status', 'mode')]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function caseList(Request $request): array
    {
        $sets = DataSet::where(fn ($q) => $q->where('mode', 'live')->orWhere('user_id', $request->user()->id))->get()->keyBy('id');
        $query = CommercialCase::whereIn('data_set_id', $sets->keys())->orderByDesc('updated_at');
        DemoPolicy::filter($query, $request);
        if ($request->filled('search')) {
            $term = mb_substr($request->string('search')->toString(), 0, 120);
            $query->where(fn ($q) => $q->where('external_id', 'like', '%'.$term.'%')->orWhere('title', 'like', '%'.$term.'%'));
        }
        if (in_array($request->input('status'), ['open', 'accepted', 'rejected', 'cancelled'], true)) {
            $query->where('status', $request->input('status'));
        }
        if (in_array($request->input('mode'), ['live', 'kit'], true)) {
            $query->whereIn('data_set_id', $sets->where('mode', $request->input('mode'))->keys());
        }
        $cases = $query->limit(200)->get();
        $ids = $cases->pluck('id');
        $latest = Evaluation::whereIn('id', Evaluation::selectRaw('MAX(id)')->whereIn('commercial_case_id', $ids)->groupBy('commercial_case_id'))->get()->keyBy('commercial_case_id');
        $recs = Recommendation::whereIn('id', Recommendation::selectRaw('MAX(id)')->whereIn('commercial_case_id', $ids)->groupBy('commercial_case_id'))->get()->keyBy('commercial_case_id');
        $recEvaluations = Evaluation::whereIn('id', $recs->pluck('evaluation_id'))->get()->keyBy('id');
        $customers = Customer::whereIn('id', $cases->pluck('customer_id')->filter())->get()->keyBy('id');

        return $cases->map(function ($case) use ($sets, $latest, $recs, $recEvaluations, $customers) {
            $current = $recs->get($case->id);
            $last = $latest->get($case->id);
            if ($current && ! $this->evaluations->isCurrentWith($case, $current, $sets[$case->data_set_id], $recEvaluations[$current->evaluation_id])) {
                $current = null;
            }

            return ['id' => $case->id, 'external_id' => $case->external_id, 'title' => $case->title, 'status' => $case->status, 'stage' => $case->stage,
                'amount_cents' => $case->amount_cents, 'revision' => $case->revision, 'mode' => $sets[$case->data_set_id]->mode,
                'set_name' => $sets[$case->data_set_id]->name, 'integrity_blocked' => $case->integrity_blocked || $sets[$case->data_set_id]->integrity_blocked,
                'customer' => $case->customer_id ? $customers->get($case->customer_id)?->company : null,
                'recommendation' => $current?->result, 'source' => $current?->source, 'publication_status' => $current?->publication_status,
                'evaluation_status' => $last?->status, 'error_message' => $last?->error_message, 'updated_at' => $case->updated_at?->toIso8601String()];
        })->all();
    }

    public function show(Request $request, CommercialCase $case): Response
    {
        $set = $this->workspace->ownCase($request->user(), $case);
        $snapshot = $this->projector->snapshot($case);
        $history = Recommendation::where('commercial_case_id', $case->id)->latest('id')->get()->map(fn ($r) => [
            ...$r->toArray(), 'is_current' => $this->evaluations->isCurrent($case, $r),
            'change_summary' => $r->validation['change_summary'] ?? null,
            'review' => HumanReview::where('recommendation_id', $r->id)->first(),
            'feedback' => RecommendationFeedback::where('recommendation_id', $r->id)->latest('id')->get(),
        ]);

        return Inertia::render('byecode/case-detail', [
            'case' => array_diff_key($case->toArray(), array_flip(['portal_token_hash'])),
            'set' => $set, 'snapshot' => $snapshot, 'rules' => app(RuleEngine::class)->assess($snapshot),
            'recommendations' => $history, 'evaluations' => Evaluation::where('commercial_case_id', $case->id)->latest('id')->limit(15)->get(['id', 'status', 'provider', 'model', 'error_code', 'error_message', 'duration_ms', 'created_at']),
            'quotes' => QuoteVersion::where('commercial_case_id', $case->id)->orderByDesc('version')->get(),
            'customer' => $case->customer_id ? Customer::find($case->customer_id) : null,
            'portalUrl' => $case->public_id ? url('/my/quotes/'.$case->public_id) : null,
            'prediction' => app(PredictionService::class)->predict($case),
        ]);
    }

    public function evaluate(Request $request, CommercialCase $case): RedirectResponse
    {
        $this->workspace->ownCase($request->user(), $case);
        $evaluation = $this->evaluations->request($case, $request->boolean('retry'));
        if ($evaluation->status === 'pending') {
            EvaluateCase::dispatch($evaluation->id);
            $evaluation->update(['dispatched_at' => now()]);
        }

        return back()->with('success', 'Evaluación solicitada. El worker procesará el caso.');
    }

    public function event(Request $request, CommercialCase $case, QuoteService $quotes): RedirectResponse
    {
        $set = $this->workspace->ownCase($request->user(), $case);
        abort_unless($set->mode === 'live', 422, 'Use la actualización oficial para modificar el kit.');
        $data = $request->validate([
            'expected_revision' => ['required', 'integer'], 'type' => ['required', Rule::in(['message.received', 'constraint.updated', 'action.executed', 'case.closed', 'observation.recorded'])],
            'message' => ['required_if:type,message.received', 'nullable', 'string', 'max:8000'], 'contact_allowed' => ['required_if:type,constraint.updated', 'nullable', 'boolean'], 'contact_not_before' => ['nullable', 'date'],
            'action' => ['required_if:type,action.executed', 'nullable', Rule::in(['contact', 'request_information'])], 'execution_reference' => ['required_if:type,action.executed', 'nullable', 'string', 'max:500'],
            'status' => ['required_if:type,case.closed', 'nullable', Rule::in(['accepted', 'rejected', 'cancelled'])], 'confirmed' => ['accepted'],
            'topic' => ['required_if:type,observation.recorded', 'nullable', 'string', 'max:100'], 'note' => ['required_if:type,observation.recorded', 'nullable', 'string', 'max:3000'], 'source' => ['required_if:type,observation.recorded', 'nullable', 'string', 'max:500'], 'verified_until' => ['required_if:type,observation.recorded', 'nullable', 'date'],
        ]);
        foreach (['contact_not_before', 'verified_until'] as $field) {
            if (! empty($data[$field])) {
                try {
                    Utc::parse($data[$field]);
                } catch (\InvalidArgumentException) {
                    throw ValidationException::withMessages([$field => 'Usa una fecha UTC ISO 8601 terminada en Z.']);
                }
            }
        }
        DB::transaction(function () use ($case, $data, $quotes, $set, $request) {
            DataSet::whereKey($set->id)->lockForUpdate()->firstOrFail();
            $case = CommercialCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            abort_if($case->revision !== (int) $data['expected_revision'], 409, 'El caso cambió; recargue la página.');
            $payload = match ($data['type']) {
                'message.received' => ['message' => $data['message'] ?? ''],
                'constraint.updated' => ['contact_allowed' => (bool) ($data['contact_allowed'] ?? false), 'contact_not_before' => empty($data['contact_not_before']) ? null : Utc::iso(CarbonImmutable::parse($data['contact_not_before'])->utc())],
                'action.executed' => ['action' => $data['action'] ?? '', 'execution_reference' => $data['execution_reference'] ?? ''],
                'case.closed' => ['status' => $data['status'] ?? ''],
                'observation.recorded' => ['topic' => $data['topic'] ?? '', 'note' => $data['note'] ?? '', 'source' => $data['source'] ?? '',
                    'verified_until' => empty($data['verified_until']) ? '' : Utc::iso(CarbonImmutable::parse($data['verified_until'])->utc())],
                default => throw ValidationException::withMessages(['type' => 'Evento no permitido.']),
            };
            $quotes->event($case, $data['type'], $payload);
            app(CustomerJourney::class)->record($case, $request->user(), $data['type'], $payload);
        }, 3);

        return back()->with('success', 'Evento registrado. La recomendación anterior se reevalúa.');
    }

    public function review(Request $request, Recommendation $recommendation): RedirectResponse
    {
        app(RecommendationReviewService::class)->review($request->user(), $recommendation, $request->all());

        return back()->with('success', 'Revisión guardada. No se ejecutó ninguna acción comercial.');
    }

    public function feedback(Request $request, Recommendation $recommendation): RedirectResponse
    {
        app(RecommendationReviewService::class)->feedback($request->user(), $recommendation, $request->all());

        return back()->with('success', 'Feedback guardado por separado de la ejecución y del resultado de venta.');
    }

    public function quoteForm(Request $request, ?CommercialCase $case = null): Response
    {
        if ($case) {
            $this->workspace->ownCase($request->user(), $case);
        }

        return Inertia::render('byecode/quote-form', [
            'case' => $case ? array_diff_key($case->toArray(), array_flip(['portal_token_hash'])) : null,
            'requestLines' => $case ? json_decode(DB::table('purchase_requests')->where('commercial_case_id', $case->id)->value('lines') ?? '[]', true) : [],
            'draft' => $case ? DB::table('quote_drafts')->where('commercial_case_id', $case->id)->first() : null,
            'quote' => $case ? QuoteVersion::where('commercial_case_id', $case->id)->latest('version')->first() : null,
            'customers' => Customer::orderBy('company')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function storeQuote(Request $request, QuoteService $service, ?CommercialCase $case = null): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'], 'title' => ['required', 'string', 'max:180'], 'expected_revision' => ['nullable', 'integer'], 'draft_revision' => ['nullable', 'integer', 'min:0'],
            'valid_until' => ['required', 'date_format:Y-m-d'], 'terms' => ['nullable', 'string', 'max:4000'],
            'authorization_reference' => ['required', 'string', 'min:5', 'max:1000'],
            'tax' => ['required', 'decimal:0,2', 'min:0', 'max:999999999'], 'shipping' => ['required', 'decimal:0,2', 'min:0', 'max:999999999'],
            'discount' => ['required', 'decimal:0,2', 'min:0', 'max:999999999'],
            'lines' => ['required', 'array', 'min:1', 'max:30'], 'lines.*.product_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'], 'lines.*.unit_price' => ['required', 'decimal:0,2', 'min:0', 'max:9999999'],
            'lines.*.supply_mode' => ['required', Rule::in(['stock', 'manufacture', 'mixed'])], 'lines.*.stock_units' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);
        $saved = $service->save($request->user(), $data, $case);

        return redirect('/cases/'.$saved->id)->with('success', 'Versión guardada. Los importes se calcularon en el servidor.');
    }

    public function customers(Request $request, CommercialProfile $commercial, CustomerDirectory $directory): Response
    {
        $rules = ['search' => 'nullable|string|max:120', 'sort' => ['nullable', Rule::in(['name', 'activity', 'paid'])],
            'preview' => 'nullable|integer|min:1'];
        foreach ($commercial->dimensions() as $key => $dimension) {
            if ($dimension['type'] !== 'text') {
                $rules[$key] = ['nullable', 'string', Rule::in(array_keys($dimension['options']))];
            }
        }
        $filters = $request->validate($rules);
        $query = $directory->query();
        DemoPolicy::filter($query, $request, 'customers.demo_run_id');
        if ($search = $filters['search'] ?? null) {
            $query->where(fn ($q) => $q->whereLike('company', '%'.$search.'%')->orWhereLike('contact_name', '%'.$search.'%')->orWhereLike('email', '%'.$search.'%'));
        }
        $commercial->filter($query, array_intersect_key($filters, $commercial->dimensions()));
        match ($filters['sort'] ?? 'name') {
            'activity' => $query->orderByRaw('CASE WHEN activity_totals.last_activity IS NULL THEN 1 ELSE 0 END')->orderByDesc('activity_totals.last_activity'),
            'paid' => $query->orderByDesc('paid_cents'),
            default => $query->orderBy('customers.company'),
        };
        $customers = $query->orderBy('customers.id')->paginate(24)->appends($request->except('preview'));
        $profiles = $commercial->summaries(array_values($customers->getCollection()->map(fn (Customer $customer) => $customer->id)->all()));
        $customers->through(fn ($customer) => $directory->row($customer, $profiles[$customer->id] ?? []));

        return Inertia::render('journey/customers', ['customers' => $customers, 'filters' => array_diff_key($filters, ['preview' => true]),
            'commercialDimensions' => $commercial->dimensions(), 'customerPreview' => function () use ($filters, $directory, $commercial) {
                if (empty($filters['preview'])) {
                    return null;
                }
                $customer = $directory->query()->where('customers.id', $filters['preview'])->firstOrFail();

                return $directory->row($customer, $commercial->summaries([$customer->id])[$customer->id] ?? []);
            }]);
    }

    public function products(Request $request): Response
    {
        return Inertia::render('byecode/products', ['products' => Product::orderBy('name')->get()]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:80', Rule::unique('products')->where('user_id', $request->user()->id)],
            'name' => ['required', 'string', 'max:180'], 'category' => ['required', Rule::in(['Playera', 'Hoodie', 'Crewneck', 'Long sleeve', 'Pants', 'Tank top', 'Short'])],
            'fit' => ['nullable', 'string', 'max:80'], 'gsm' => ['nullable', 'integer', 'min:1', 'max:1500'], 'composition' => ['nullable', 'string', 'max:180'],
            'color' => ['nullable', 'string', 'max:80'], 'size' => ['nullable', 'string', 'max:30'],
            'unit_price' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999'], 'moq' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:10000000'], 'source' => ['required', 'string', 'max:500'],
        ]);
        $data['unit_price_cents'] = isset($data['unit_price']) ? Money::cents($data['unit_price']) : null;
        unset($data['unit_price']);
        Product::create(['user_id' => $request->user()->id, ...$data, 'verified_at' => now()]);

        return back()->with('success', 'Producto registrado. La existencia es una observación, no una reserva.');
    }

    public function portalLink(Request $request, CommercialCase $case): RedirectResponse
    {
        $set = $this->workspace->ownCase($request->user(), $case);
        abort_unless($set->mode === 'live' && QuoteVersion::where('commercial_case_id', $case->id)->exists(), 422);
        $case->update(['public_id' => $case->public_id ?: (string) Str::uuid()]);
        $request->session()->put('portal_link_'.$case->id, url('/my/quotes/'.$case->public_id));

        return back()->with('success', 'Enlace de cuenta disponible.');
    }
}
