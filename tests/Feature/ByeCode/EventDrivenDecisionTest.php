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
