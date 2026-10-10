<?php

use App\Domain\Cases\CaseProjector;
use App\Domain\Cases\CustomerJourney;
use App\Domain\Cases\ProductionCosts;
use App\Domain\Cases\QuoteService;
use App\Domain\Decision\BehaviorEvidence;
use App\Domain\Decision\CommercialPlans;
use App\Domain\Decision\DecisionValidator;
use App\Domain\Decision\RuleEngine;
use App\Domain\Evaluation\EvaluationService;
use App\Jobs\EnrichCustomer;
use App\Jobs\RefreshCaseDecision;
use App\Models\Product;
use App\Models\QuoteVersion;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    Mail::fake();
    Queue::fake([RefreshCaseDecision::class, EnrichCustomer::class]);
    config(['integrations.openai.api_key' => null, 'onboarding.mail_enabled' => false, 'inertia.ssr.enabled' => false]);
    $this->seller = User::factory()->create(['role' => 'commerce']);
    $this->buyer = User::factory()->create(['role' => 'customer']);
    $style = DB::table('product_styles')->insertGetId(['name' => 'Playera', 'category' => 'Playeras', 'details' => '{}', 'published' => true, 'created_at' => now(), 'updated_at' => now()]);
    $this->product = Product::create(['user_id' => $this->seller->id, 'style_id' => $style, 'sku' => 'COST-M', 'name' => 'Playera', 'category' => 'Playeras', 'color' => 'Negro', 'size' => 'M', 'published' => true]);
    $this->case = app(CustomerJourney::class)->submit($this->buyer, ['submission_id' => (string) Str::uuid(),
        'lines' => [['product_id' => $this->product->id, 'quantity' => 75]], 'message' => 'Necesito playeras.']);
    $this->quoteData = ['customer_id' => $this->case->customer_id, 'title' => 'Cotización', 'valid_until' => now()->addWeek()->format('Y-m-d'),
        'authorization_reference' => 'Revisión del vendedor', 'tax' => '100.00', 'shipping' => '50.00', 'discount' => '25.00', 'expected_revision' => $this->case->revision,
        'lines' => [['product_id' => $this->product->id, 'quantity' => 75, 'line_total' => '3750.00']]];
});

it('accepts submitted request evidence in plans without admitting foreign or invented events', function () {
    $other = app(CustomerJourney::class)->submit($this->buyer, ['submission_id' => (string) Str::uuid(),
        'lines' => [['product_id' => $this->product->id, 'quantity' => 5]], 'message' => 'Otra solicitud.']);
    $snapshot = app(CaseProjector::class)->snapshot($this->case);
    $id = DB::table('activity_events')->where('commercial_case_id', $this->case->id)->where('type', 'request.submitted')->value('event_id');
    $foreign = DB::table('activity_events')->where('commercial_case_id', $other->id)->where('type', 'request.submitted')->value('event_id');
    $catalog = app(BehaviorEvidence::class)->catalog($snapshot);
    expect($catalog[$id]['summary'])->toContain('Solicitud enviada', '75 unidades')->and($catalog)->not->toHaveKey($foreign);
    $candidate = ['action' => 'escalate', 'signals' => [], 'plans' => [[
        'action' => 'escalate', 'title' => 'Revisar solicitud', 'reason' => 'La solicitud necesita revisión interna.',
        'steps' => ['Revisar cantidades.'], 'message' => null, 'proposed_total_cents' => null,
        'additional_delivery_days' => null, 'checks' => [], 'evidence_event_ids' => [$id],
    ]]];
    app(CommercialPlans::class)->validate($snapshot, $candidate);
    foreach ([$foreign, 'invented-event'] as $invalidId) {
        $candidate['plans'][0]['evidence_event_ids'] = [$invalidId];
        expect(fn () => app(CommercialPlans::class)->validate($snapshot, $candidate))->toThrow(InvalidArgumentException::class);
    }
    Http::assertNothingSent();
});

it('calculates manufacturing costs for requests and quotes without treating unknown garments as free', function () {
    $before = app(CaseProjector::class)->snapshot($this->case)['production_economics'];
    expect($before['production_cost_cents'])->toBe(354750)->and($before['net_merchandise_cents'])->toBeNull();
    $case = app(QuoteService::class)->save($this->seller, $this->quoteData, $this->case);
    $quote = QuoteVersion::firstOrFail();
    $cost = app(CaseProjector::class)->snapshot($case)['production_economics'];
    expect($cost['playera_unit_cost_cents'])->toBe(4730)
        ->and($cost['production_cost_cents'])->toBe(354750)
        ->and($cost['net_merchandise_cents'])->toBe(372500)
        ->and($cost['contribution_before_other_costs_cents'])->toBe(17750)
        ->and($cost['minimum_proposed_quote_total_cents'])->toBe(369750);
    $lines = $quote->lines;
    $lines[] = [...$lines[0], 'line_id' => 'L2', 'category' => 'Sudaderas', 'quantity' => 2];
    $quote->lines = $lines;
    $mixed = app(ProductionCosts::class)->context($case, $quote);
    expect($mixed['all_lines_costed'])->toBeFalse()->and($mixed['production_cost_cents'])->toBeNull()
        ->and($mixed['lines'][1]['unit_cost_cents'])->toBeNull();
    $this->actingAs($this->buyer)->get('/my/quotes/'.$case->public_id)->assertInertia(fn (Assert $p) => $p
        ->missing('production_economics')->missing('quotes.0.production_economics')->where('analysis', null)->where('previousAnalysis', null));
});

it('records exact partial counteroffers without accepting or overwriting the published quote', function () {
    $case = app(QuoteService::class)->save($this->seller, $this->quoteData, $this->case);
    $original = QuoteVersion::firstOrFail()->getRawOriginal();
    $data = ['event_id' => (string) Str::uuid(), 'quote_version' => 1, 'kind' => 'accept',
        'lines' => [['line_id' => 'L1', 'quantity' => 25, 'proposed_line_total' => '1000.01']], 'conditions' => 'Este es mi presupuesto.'];
    $url = '/journey/'.$case->id.'/decision';
    $this->actingAs($this->buyer)->post($url, $data)->assertSessionHasNoErrors();
    $row = DB::table('buyer_decisions')->sole();
    expect($row->kind)->toBe('change')->and(json_decode($row->lines, true)[0])->toBe(['line_id' => 'L1', 'quantity' => 25, 'proposed_total_cents' => 100001])
        ->and(QuoteVersion::firstOrFail()->getRawOriginal())->toBe($original)->and($case->fresh()->status)->toBe('open');
    $revision = $case->fresh()->revision;
    $this->post($url, $data)->assertSessionHasNoErrors();
    expect(DB::table('buyer_decisions')->count())->toBe(1)->and($case->fresh()->revision)->toBe($revision);
    $data['lines'][0]['proposed_line_total'] = '999.00';
    $this->post($url, $data)->assertConflict();
    $this->actingAs($this->seller)->get('/my/quotes/'.$case->public_id)->assertInertia(fn (Assert $p) => $p->where('decisions.0.lines.0.proposed_total_cents', 100001));
    $snapshot = app(CaseProjector::class)->snapshot($case->fresh());
    expect(collect($snapshot['events'])->contains(fn ($e) => str_contains($e['data']['message'] ?? '', '100001')))->toBeTrue();
    Http::assertNothingSent();
});

it('rejects malformed counteroffers and preserves ordinary acceptance', function () {
    $case = app(QuoteService::class)->save($this->seller, $this->quoteData, $this->case);
    $url = '/journey/'.$case->id.'/decision';
    $this->actingAs($this->buyer);
    foreach (['-1', '0', '10.123', '1000000000', 'NaN'] as $price) {
        $this->post($url, ['event_id' => (string) Str::uuid(), 'quote_version' => 1, 'kind' => 'change',
            'lines' => [['line_id' => 'L1', 'quantity' => 25, 'proposed_line_total' => $price]]])->assertSessionHasErrors();
    }
    $this->post($url, ['event_id' => (string) Str::uuid(), 'quote_version' => 1, 'kind' => 'accept',
        'lines' => [['line_id' => 'L1', 'quantity' => 25, 'proposed_line_total' => '1250.00']]])->assertSessionHasNoErrors();
    $row = DB::table('buyer_decisions')->sole();
    expect($row->kind)->toBe('accept')->and(json_decode($row->lines, true)[0])->not->toHaveKey('proposed_total_cents');
});

it('keeps the last analysis during refresh and never treats it as current after new events or expiration', function () {
    $service = app(EvaluationService::class);
    $case = $this->case->fresh();
    $evaluation = $service->request($case);
    $evaluation->update(['status' => 'completed']);
    $rec = Recommendation::create(['commercial_case_id' => $case->id, 'evaluation_id' => $evaluation->id, 'case_revision' => $case->revision,
        'source' => 'openai', 'publication_status' => 'awaiting_review', 'result' => ['reason' => 'Última recomendación'],
        'signals' => [], 'validation' => [], 'alternatives' => [], 'valid_until' => now()->addHour(), 'created_at' => now()]);
    $url = '/my/quotes/'.$case->public_id;
    $this->actingAs($this->seller);
    for ($i = 0; $i < 2; $i++) {
        $this->get($url)->assertInertia(fn (Assert $p) => $p->where('analysis.result.reason', 'Última recomendación')->where('analysisScheduled', false));
    }
    expect($case->fresh()->revision)->toBe($case->revision);
    app(QuoteService::class)->event($case, 'message.received', ['message' => 'Ahora necesito otro precio.']);
    $this->get($url)->assertInertia(fn (Assert $p) => $p->where('analysis', null)->where('previousAnalysis.result.reason', 'Última recomendación')->where('analysisScheduled', true));
    $rec->update(['valid_until' => now()->subMinute()]);
    $this->get($url)->assertInertia(fn (Assert $p) => $p->where('analysis', null)->where('previousAnalysis.result.reason', 'Última recomendación'));
    $this->actingAs($this->buyer)->get($url)->assertInertia(fn (Assert $p) => $p->where('analysis', null)->where('previousAnalysis', null)->where('evaluation', null));
    $rec->update(['publication_status' => 'rejected']);
    $this->actingAs($this->seller)->get($url)->assertInertia(fn (Assert $p) => $p->where('previousAnalysis', null));
    Http::assertNothingSent();
});

it('enforces the manufacturing floor on AI plans and allows evidenced inaction without closing the case', function () {
    $case = app(QuoteService::class)->save($this->seller, $this->quoteData, $this->case);
    app(QuoteService::class)->event($case, 'message.received', ['message' => 'Solo puedo pagar 20 pesos por playera y no cambiaré la cantidad.'], 'budget-objection');
    $snapshot = app(CaseProjector::class)->snapshot($case->fresh());
    $candidate = ['action' => 'escalate', 'action_order' => ['escalate', ...array_diff(RuleEngine::ACTIONS, ['escalate'])],
        'timing' => 'within_24h', 'scheduled_for' => null, 'signals' => [['kind' => 'objection', 'event_id' => 'budget-objection', 'quote' => 'Solo puedo pagar 20 pesos por playera']],
        'missing_information' => ['authorization'], 'confidence' => 'medium', 'risk_level' => 'medium', 'requires_human_review' => true,
        'plans' => [['action' => 'escalate', 'title' => 'Revisar viabilidad', 'reason' => 'El presupuesto está por debajo del costo.',
            'steps' => ['Validar internamente.'], 'message' => null, 'proposed_total_cents' => 369749, 'additional_delivery_days' => null,
            'checks' => ['Validar otros costos.'], 'evidence_event_ids' => ['budget-objection']]]];
    expect(fn () => app(CommercialPlans::class)->validate($snapshot, $candidate))->toThrow(InvalidArgumentException::class);
    $candidate['plans'][0]['proposed_total_cents'] = 369750;
    app(CommercialPlans::class)->validate($snapshot, $candidate);
    $unknown = $snapshot;
    $unknown['production_economics']['all_lines_costed'] = false;
    expect(fn () => app(CommercialPlans::class)->validate($unknown, $candidate))->toThrow(InvalidArgumentException::class);
    $candidate['action'] = 'no_action';
    $candidate['action_order'] = ['no_action', ...array_diff(RuleEngine::ACTIONS, ['no_action'])];
    $candidate['timing'] = 'not_applicable';
    $candidate['plans'][0]['action'] = 'no_action';
    $candidate['plans'][0]['proposed_total_cents'] = null;
    $compiled = app(DecisionValidator::class)->compile($snapshot, $candidate);
    expect($compiled['result']['action'])->toBe('no_action')->and($case->fresh()->status)->toBe('open');
});
