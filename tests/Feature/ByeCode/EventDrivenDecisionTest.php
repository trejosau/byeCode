<?php

use App\Domain\Cases\CaseProjector;
use App\Domain\Cases\CustomerJourney;
use App\Domain\Cases\CustomerProfile;
use App\Domain\Cases\QuoteService;
use App\Domain\Decision\ActionRanking;
use App\Domain\Decision\DecisionValidator;
use App\Domain\Decision\RuleEngine;
use App\Domain\Evaluation\EvaluationService;
use App\Domain\Events\EventProfile;
use App\Domain\Events\ImportService;
use App\Domain\Prediction\PredictionService;
use App\Jobs\EnrichCustomer;
use App\Jobs\RefreshCaseDecision;
use App\Models\CommercialCase;
use App\Models\Customer;
use App\Models\DataSet;
use App\Models\Evaluation;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('local');
    Queue::fake([RefreshCaseDecision::class, EnrichCustomer::class]);
    config(['integrations.openai.api_key' => null, 'inertia.ssr.enabled' => false]);
    $this->seller = User::factory()->create(['role' => 'commerce']);
    $this->buyer = User::factory()->create(['role' => 'customer']);
    $this->customer = Customer::create(['user_id' => $this->seller->id, 'account_id' => $this->buyer->id, 'company' => 'Cliente sintético']);
    $this->set = DataSet::create(['user_id' => $this->seller->id, 'mode' => 'live', 'name' => 'Operación de prueba']);
    app(ImportService::class)->import($this->set, json_encode(['event_id' => 'opening', 'case_id' => 'LIVE-1', 'event_type' => 'case.opened',
        'occurred_at' => now()->subHour()->format('Y-m-d\TH:i:s\Z'), 'data' => ['status' => 'open', 'proposal_amount_mxn' => 1000]]), 'Apertura', EventProfile::BUSINESS);
    $this->case = CommercialCase::where('data_set_id', $this->set->id)->firstOrFail();
    $this->case->update(['customer_id' => $this->customer->id]);
    Queue::fake([RefreshCaseDecision::class, EnrichCustomer::class]);
});

function rankedCandidate(): array
{
    return ['action' => 'resolve_objection', 'action_order' => ['resolve_objection', 'escalate', 'wait', 'no_action', 'contact', 'request_information'],
        'timing' => 'within_24h', 'scheduled_for' => null, 'signals' => [['kind' => 'question', 'event_id' => 'question', 'quote' => '¿Qué composición tiene?']],
        'missing_information' => [], 'confidence' => 'medium', 'risk_level' => 'low', 'requires_human_review' => true];
}

it('aggregates navigation beyond the recent window with version and temporal isolation', function () {
    $at = now()->toImmutable();
    $insert = function (string $id, string $type, array $data, array $extra = []) use ($at) {
        DB::table('activity_events')->insert([...['event_id' => $id, 'customer_id' => $this->customer->id,
            'commercial_case_id' => $this->case->id, 'actor' => 'customer', 'source' => 'browser_unverified',
            'type' => $type, 'data' => json_encode($data), 'occurred_at' => $at->subMinute(), 'ingested_at' => $at->subMinute()], ...$extra]);
    };
    foreach (['products', 'prices', 'terms', 'decision', 'conversation'] as $section) {
        $insert('visible-'.$section, 'ui.section_visible', ['section' => $section, 'version' => 1]);
    }
    for ($i = 0; $i < 40; $i++) {
        $insert('duration-'.$i, 'ui.section_visibility_duration', ['section' => 'prices', 'version' => 1, 'duration_ms' => 30000]);
    }
    $insert('v2', 'ui.section_visibility_duration', ['section' => 'prices', 'version' => 2, 'duration_ms' => 5000]);
    $insert('scroll', 'ui.scroll_depth_reached', ['version' => 1, 'depth' => 75]);
    $insert('future', 'ui.section_visibility_duration', ['section' => 'prices', 'version' => 1, 'duration_ms' => 30000], ['occurred_at' => $at->addMinute()]);
    $insert('late', 'ui.section_visible', ['section' => 'prices'], ['ingested_at' => $at->addMinute()]);
    $insert('unassigned', 'ui.section_visible', ['section' => 'prices'], ['commercial_case_id' => null]);
    $insert('staff', 'ui.section_visible', ['section' => 'prices'], ['actor' => 'commerce']);
    $insert('payment', 'sale.payment', ['amount_cents' => 25000], ['actor' => 'commerce', 'source' => 'application']);
    $snapshot = app(CaseProjector::class)->snapshot($this->case->fresh(), $at);
    $context = $snapshot['behavior_context'];
    expect($context['sections'])->toHaveCount(6)
        ->and(collect($context['sections'])->firstWhere('section', 'prices')['active_duration_ms'])->toBe(1200000)
        ->and($context['sections'][5]['active_duration_ms'])->toBe(5000)
        ->and($context['scroll'][0]['max_depth'])->toBe(75)
        ->and(array_column($context['events'], 'event_id'))->toContain('visible-products', 'duration-39', 'payment')
        ->not->toContain('future', 'late', 'unassigned', 'staff')
        ->and(collect($context['events'])->firstWhere('event_id', 'payment')['summary'])->toContain('250.00 MXN');
    $decision = app(RuleEngine::class)->deterministic($snapshot);
    expect($decision['action'])->toBe('wait');
    Http::assertNothingSent();
});

it('validates and displays cited behavior evidence without promoting it to explicit intent', function () {
    app(QuoteService::class)->event($this->case, 'message.received', ['message' => '¿Qué composición tiene?'], 'question');
    app(CustomerJourney::class)->record($this->case, $this->buyer, 'buyer.change', ['conditions' => 'Necesito otra tela.', 'quote_version' => 1], 'change-proof');
    $snapshot = app(CaseProjector::class)->snapshot($this->case->fresh());
    $candidate = rankedCandidate();
    $candidate['plans'] = [['action' => 'resolve_objection', 'title' => 'Aclarar la tela',
        'reason' => 'Preguntó la composición y solicitó otra tela; aclararla permite revisar el cambio.',
        'steps' => ['Verificar composición.'], 'message' => null, 'proposed_total_cents' => null,
        'additional_delivery_days' => null, 'checks' => [], 'evidence_event_ids' => ['question', 'change-proof']]];
    $compiled = app(DecisionValidator::class)->compile($snapshot, $candidate);
    $ranking = app(ActionRanking::class)->build($snapshot, $compiled['result'], $compiled['signals'], $candidate);
    expect(array_column($ranking[0]['evidence'], 'event_id'))->toContain('change-proof', 'question')
        ->and(collect($ranking[0]['evidence'])->firstWhere('event_id', 'change-proof')['summary'])->toContain('Necesito otra tela.');
    $candidate['plans'][0]['evidence_event_ids'][] = 'foreign-or-future';
    expect(fn () => app(DecisionValidator::class)->compile($snapshot, $candidate))->toThrow(InvalidArgumentException::class);
    $candidate['plans'][0]['evidence_event_ids'] = ['change-proof'];
    $candidate['signals'][0] = ['kind' => 'interest', 'event_id' => 'change-proof', 'quote' => 'Necesito otra tela.'];
    expect(fn () => app(DecisionValidator::class)->compile($snapshot, $candidate))->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
});

function rankResponse(array $candidate): array
{
    return ['status' => 'completed', 'model' => config('byecode.model'), 'usage' => ['input_tokens' => 200, 'output_tokens' => 100],
        'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($candidate)]]]]];
}

it('dispatches activity only after commit and rolls back both data and dispatch intentions', function () {
    config(['integrations.openai.api_key' => 'fake-key']);
    $before = $this->case->revision;
    DB::beginTransaction();
    app(CustomerJourney::class)->record($this->case, $this->seller, 'sale.payment', ['amount_cents' => 100], 'rolled-back');
    Queue::assertNothingPushed();
    DB::rollBack();
    expect(DB::table('activity_events')->where('event_id', 'rolled-back')->exists())->toBeFalse();
    expect($this->case->fresh()->revision)->toBe($before);
    Queue::assertNothingPushed();
    DB::transaction(fn () => app(CustomerJourney::class)->record($this->case->fresh(), $this->seller, 'sale.payment', ['amount_cents' => 200], 'committed'));
    Queue::assertPushed(RefreshCaseDecision::class, fn ($job) => $job->caseId === $this->case->id);
    Queue::assertPushed(EnrichCustomer::class, fn ($job) => $job->customerId === $this->customer->id);
});

it('invalidates a prior decision on a payment without treating payment as closure', function () {
    $service = app(EvaluationService::class);
    (new RefreshCaseDecision($this->case->id))->handle($service);
    expect($service->current($this->case->fresh()))->not->toBeNull();
    $this->actingAs($this->seller)->post('/journey/'.$this->case->id.'/result', [
        'event_id' => (string) Str::uuid(), 'kind' => 'payment', 'amount' => '100.00', 'reference' => 'Pago sintético de prueba', 'occurred_at' => now()->subMinute()->toIso8601String(), 'close' => false,
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($service->current($this->case->fresh()))->toBeNull()->and($this->case->fresh()->status)->toBe('open');
    Queue::assertPushed(RefreshCaseDecision::class);
    (new RefreshCaseDecision($this->case->id))->handle($service);
    $latest = Evaluation::latest('id')->first();
    expect($latest->snapshot['customer_context']['paid_cents'])->toEqual(10000);
    expect($latest->snapshot['activity_context']['events'][0]['type'])->toBe('sale.payment');
    expect(Recommendation::count())->toBe(2);
    expect(Recommendation::latest('id')->first()->validation['change_summary']['new_activity_count'])->toBe(1);
    Http::assertNothingSent();
});

it('processes the latest committed revision once when several events arrive together', function () {
    config(['integrations.openai.api_key' => 'fake-key']);
    Http::fake(['api.openai.com/*' => Http::response(rankResponse(rankedCandidate()))]);
    app(QuoteService::class)->event($this->case, 'message.received', ['message' => '¿Qué composición tiene?'], 'question');
    foreach (['one', 'two', 'three'] as $id) {
        app(CustomerJourney::class)->record($this->case->fresh(), $this->seller, 'sale.payment', ['amount_cents' => 1], $id);
    }
    $job = new RefreshCaseDecision($this->case->id);
    $job->handle(app(EvaluationService::class));
    $job->handle(app(EvaluationService::class));
    expect(Recommendation::count())->toBe(1);
    expect(Evaluation::where('status', 'pending')->count())->toBe(0);
    $recommendation = Recommendation::first();
    expect($recommendation->case_revision)->toBe($this->case->fresh()->revision);
    expect($recommendation->alternatives[0]['action'])->toBe('resolve_objection')->and($recommendation->alternatives[0]['rank'])->toBe(1);
    expect(count($recommendation->result))->toBe(11)->and(array_column($recommendation->alternatives, 'probability'))->toBe(array_fill(0, 6, null));
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['text']['format']['name'] === 'byecode_semantic_v2');
});

it('schedules automatic browser activity and ignores duplicate receipts without new revisions', function () {
    config(['integrations.openai.api_key' => 'fake-key']);
    $payload = ['events' => [['event_id' => (string) Str::uuid(), 'type' => 'ui.section_visible', 'case_id' => $this->case->id, 'data' => ['section' => 'price']]]];
    $this->actingAs($this->buyer)->post('/activity', $payload)->assertNoContent();
    $revision = $this->case->fresh()->revision;
    Queue::assertPushed(RefreshCaseDecision::class, 1);
    Queue::assertPushed(EnrichCustomer::class, 1);
    $this->post('/activity', $payload)->assertNoContent();
    expect($this->case->fresh()->revision)->toBe($revision);
    Queue::assertPushed(RefreshCaseDecision::class, 1);
    Queue::assertPushed(EnrichCustomer::class, 1);
    expect(app(CaseProjector::class)->snapshot($this->case->fresh())['activity_context']['events'][0]['source'])->toBe('browser_unverified');
    Http::assertNothingSent();
});

it('batches reading events and keeps the latest section context without manual evaluation', function () {
    $this->freezeTime();
    $payload = ['events' => [
        ['event_id' => (string) Str::uuid(), 'type' => 'ui.section_visible', 'case_id' => $this->case->id, 'data' => ['section' => 'terms']],
        ['event_id' => (string) Str::uuid(), 'type' => 'ui.section_visibility_duration', 'case_id' => $this->case->id, 'data' => ['section' => 'terms', 'duration_ms' => 30000]],
    ]];
    $before = $this->case->fresh()->revision;
    $this->actingAs($this->buyer)->post('/activity', $payload)->assertNoContent();
    expect($this->case->fresh()->revision)->toBe($before + 1);
    expect($this->case->fresh()->next_review_at->timestamp)->toBe(now()->addSeconds(20)->timestamp);
    Queue::assertPushed(RefreshCaseDecision::class, fn ($job) => $job->delay->timestamp === now()->addSeconds(20)->timestamp);
    $this->travel(10)->seconds();
    $this->post('/activity', ['events' => [['event_id' => (string) Str::uuid(), 'type' => 'ui.scroll_depth_reached',
        'case_id' => $this->case->id, 'data' => ['depth' => 100]]]])->assertNoContent();
    expect($this->case->fresh()->next_review_at->timestamp)->toBe(now()->addSeconds(10)->timestamp);
    (new RefreshCaseDecision($this->case->id))->handle(app(EvaluationService::class));
    $evaluation = Evaluation::latest('id')->firstOrFail();
    expect($evaluation->snapshot['activity_context']['events'])->toHaveCount(3);
    expect($evaluation->snapshot['activity_context']['events'][1]['data']['section'])->toBe('terms');
    expect(Recommendation::latest('id')->firstOrFail()->result['action'])->toBe('wait');
    Http::assertNothingSent();
});

it('automatically schedules buyer messages and ignores staff reading telemetry', function () {
    $before = $this->case->fresh()->revision;
    $this->actingAs($this->seller)->post('/activity', ['events' => [['event_id' => (string) Str::uuid(),
        'type' => 'ui.section_visible', 'case_id' => $this->case->id, 'data' => ['section' => 'terms']]]])->assertNoContent();
    expect($this->case->fresh()->revision)->toBe($before);
    Queue::assertNothingPushed();
    $this->actingAs($this->buyer)->post('/journey/'.$this->case->id.'/messages', [
        'event_id' => (string) Str::uuid(), 'body' => '¿Qué composición tiene?',
    ])->assertRedirect()->assertSessionHasNoErrors();
    Queue::assertPushed(RefreshCaseDecision::class);
    expect($this->case->fresh()->next_review_at->lessThanOrEqualTo(now()))->toBeTrue();
    Http::assertNothingSent();
});

it('ranks concrete negotiation plans without changing the quoted price or delivery', function () {
    app(QuoteService::class)->event($this->case, 'message.received', ['message' => 'Está caro, puedo esperar tres días más.'], 'price-objection');
    $snapshot = app(CaseProjector::class)->snapshot($this->case->fresh());
    $snapshot['quote'] = ['total_cents' => 200000];
    $snapshot['production_economics'] = ['all_lines_costed' => true, 'minimum_proposed_quote_total_cents' => 47300];
    $candidate = [...rankedCandidate(), 'action' => 'escalate',
        'action_order' => ['escalate', 'wait', 'no_action', 'resolve_objection', 'contact', 'request_information'],
        'signals' => [['kind' => 'objection', 'event_id' => 'price-objection', 'quote' => 'Está caro']],
        'missing_information' => ['authorization', 'delivery_confirmation'],
        'plans' => [
            ['action' => 'escalate', 'title' => 'Revisar descuento a cambio de tres días adicionales',
                'reason' => 'El cliente objeta el precio y ofrece flexibilidad de entrega.',
                'steps' => ['Comprobar margen y capacidad antes de ofrecer el cambio.'],
                'message' => 'Podemos revisar un ajuste si tienes flexibilidad en la entrega; te confirmo tras validarlo.',
                'proposed_total_cents' => 190000, 'additional_delivery_days' => 3,
                'checks' => ['Validar costos y margen.', 'Confirmar capacidad de entrega.'], 'evidence_event_ids' => ['price-objection']],
            ['action' => 'wait', 'title' => 'Mantener el precio mientras se valida el margen',
                'reason' => 'Evita comprometer una concesión sin conocer su viabilidad.',
                'steps' => ['Esperar la confirmación interna.'], 'message' => null,
                'proposed_total_cents' => null, 'additional_delivery_days' => null,
                'checks' => [], 'evidence_event_ids' => ['price-objection']],
        ]];
    $compiled = app(DecisionValidator::class)->compile($snapshot, $candidate);
    $rows = app(ActionRanking::class)->build($snapshot, $compiled['result'], $compiled['signals'], $candidate);
    expect($rows[0]['plan']['proposed_total_cents'])->toBe(190000)
        ->and($rows[0]['plan']['additional_delivery_days'])->toBe(3)
        ->and($rows[0]['requires_human_review'])->toBeTrue()
        ->and($rows[1]['action'])->toBe('wait')->and($rows[1]['plan']['message'])->toBeNull()
        ->and($snapshot['quote']['total_cents'])->toBe(200000);
    foreach (['foreign_evidence', 'missing_checks', 'excess_price', 'fractional_days', 'wait_message'] as $invalid) {
        $bad = $candidate;
        match ($invalid) {
            'foreign_evidence' => $bad['plans'][0]['evidence_event_ids'] = ['another-case'],
            'missing_checks' => $bad['plans'][0]['checks'] = [],
            'excess_price' => $bad['plans'][0]['proposed_total_cents'] = 999999,
            'fractional_days' => $bad['plans'][0]['additional_delivery_days'] = 1.5,
            'wait_message' => $bad['plans'][1]['message'] = 'Te contacto ahora.',
        };
        expect(fn () => app(DecisionValidator::class)->compile($snapshot, $bad))->toThrow(InvalidArgumentException::class);
    }
    Http::assertNothingSent();
});

it('persists concrete model plans in the automatic evaluation', function () {
    config(['integrations.openai.api_key' => 'fake-key']);
    app(QuoteService::class)->event($this->case, 'message.received', ['message' => '¿Qué composición tiene?'], 'question');
    $candidate = [...rankedCandidate(), 'plans' => [[
        'action' => 'resolve_objection', 'title' => 'Aclarar la composición de la prenda',
        'reason' => 'El cliente pregunta por el material.', 'steps' => ['Revisar la ficha antes de responder.'],
        'message' => 'Voy a revisar la ficha de la prenda para aclararte la composición.',
        'proposed_total_cents' => null, 'additional_delivery_days' => null, 'checks' => ['Verificar la ficha.'],
        'evidence_event_ids' => ['question'],
    ]]];
    Http::fake(['api.openai.com/*' => Http::response(rankResponse($candidate))]);
    (new RefreshCaseDecision($this->case->id))->handle(app(EvaluationService::class));
    $rec = Recommendation::latest('id')->firstOrFail();
    expect($rec->alternatives[0]['plan']['title'])->toBe('Aclarar la composición de la prenda')
        ->and($rec->publication_status)->toBe('awaiting_review');
    Http::assertSentCount(1);
});

it('keeps blocked contact actions out of the ranking under each hard restriction', function (string $restriction) {
    app(QuoteService::class)->event($this->case, 'message.received', ['message' => '¿Qué composición tiene?'], 'question');
    $snapshot = app(CaseProjector::class)->snapshot($this->case->fresh());
    match ($restriction) {
        'R1' => $snapshot['state']['status'] = 'accepted',
        'R2' => $snapshot['state']['contact_allowed'] = false,
        'R3' => $snapshot['state']['contact_not_before'] = now()->addDay()->format('Y-m-d\TH:i:s\Z'),
        'R4' => $snapshot['metrics'] = [...$snapshot['metrics'], 'last_contact_at' => now()->subHour()->format('Y-m-d\TH:i:s\Z'), 'new_message_after_contact' => false],
    };
    $action = $restriction === 'R1' ? 'no_action' : 'escalate';
    $candidate = [...rankedCandidate(), 'action' => $action, 'action_order' => array_values(array_unique([$action, ...RuleEngine::ACTIONS])), 'timing' => $action === 'no_action' ? 'not_applicable' : 'within_24h'];
    $compiled = app(DecisionValidator::class)->compile($snapshot, $candidate);
    $ranking = app(ActionRanking::class)->build($snapshot, $compiled['result'], $compiled['signals'], $candidate);
    foreach ($ranking as $row) {
        if (in_array($row['action'], RuleEngine::CONTACT, true)) {
            expect($row['rank'])->toBeNull()->and($row['allowed'])->toBeFalse()->and($row['excluded_by'])->toContain($restriction);
        }
    }
    expect($ranking[0]['action'])->toBe($action);
})->with(['R1', 'R2', 'R3', 'R4']);

it('rejects duplicate ranking actions and invented confidence percentages', function () {
    app(QuoteService::class)->event($this->case, 'message.received', ['message' => '¿Qué composición tiene?'], 'question');
    $snapshot = app(CaseProjector::class)->snapshot($this->case->fresh());
    expect(fn () => app(DecisionValidator::class)->compile($snapshot, [...rankedCandidate(), 'action_order' => array_fill(0, 6, 'resolve_objection')]))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(DecisionValidator::class)->compile($snapshot, [...rankedCandidate(), 'probability' => 0.95]))->toThrow(InvalidArgumentException::class);
    expect(app(PredictionService::class)->predict($this->case->fresh()))->toMatchArray(['available' => false]);
});

it('continues successful profile batches automatically but does not repeat a failed call', function () {
    config(['integrations.openai.api_key' => 'fake-key']);
    foreach (range(1, 31) as $n) {
        DB::table('activity_events')->insert(['event_id' => 'profile-'.$n, 'customer_id' => $this->customer->id, 'account_id' => $this->buyer->id,
            'type' => 'catalog.product_opened', 'actor' => 'customer', 'source' => 'browser_unverified', 'data' => '{}', 'occurred_at' => now(), 'ingested_at' => now()]);
    }
    Http::fake(['api.openai.com/*' => Http::sequence()->push(rankResponse(['facts' => []]))->push(['error' => 'test failure'], 503)]);
    $job = new EnrichCustomer($this->customer->id);
    $job->handle(app(CustomerProfile::class));
    expect(DB::table('profile_runs')->where('status', 'complete')->value('cutoff'))->toBe(30);
    Queue::assertPushed(EnrichCustomer::class, 1);
    Queue::fake([EnrichCustomer::class, RefreshCaseDecision::class]);
    $job->handle(app(CustomerProfile::class));
    $job->handle(app(CustomerProfile::class));
    expect(DB::table('profile_runs')->where('status', 'failed')->count())->toBe(1);
    Queue::assertNotPushed(EnrichCustomer::class);
    Http::assertSentCount(2);
});

it('records an explicit blocked analysis without an API key', function () {
    app(QuoteService::class)->event($this->case, 'message.received', ['message' => '¿Qué composición tiene?'], 'question');
    (new RefreshCaseDecision($this->case->id))->handle(app(EvaluationService::class));
    expect(Evaluation::latest('id')->first()->error_code)->toBe('missing_api_key')->and(Recommendation::count())->toBe(0);
    Http::assertNothingSent();
});

it('invalidates other open cases when a customer-wide payment indicator changes', function () {
    $other = CommercialCase::create(['data_set_id' => $this->set->id, 'customer_id' => $this->customer->id, 'external_id' => 'LIVE-2', 'title' => 'Otra oportunidad', 'status' => 'open']);
    $before = $other->fresh()->revision;
    app(CustomerJourney::class)->record($this->case, $this->seller, 'sale.payment', ['amount_cents' => 100], 'shared-payment');
    expect($other->fresh()->revision)->toBe($before + 1);
    Queue::assertPushed(RefreshCaseDecision::class, fn ($job) => $job->caseId === $other->id);
});
