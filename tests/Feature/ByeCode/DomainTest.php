<?php

use App\Domain\Cases\CaseProjector;
use App\Domain\Decision\DecisionValidator;
use App\Domain\Decision\RuleEngine;
use App\Domain\Evaluation\BudgetLedger;
use App\Domain\Evaluation\EvaluationService;
use App\Domain\Events\ImportService;
use App\Domain\Support\Utc;
use App\Models\AiUsage;
use App\Models\CaseEvent;
use App\Models\CommercialCase;
use App\Models\DataSet;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    config(['integrations.openai.api_key' => null]);
    $this->set = DataSet::create(['user_id' => User::factory()->create()->id, 'name' => 'Kit de prueba', 'mode' => 'kit', 'evaluation_at' => '2026-10-09T16:00:00Z']);
    $this->import = app(ImportService::class);
    $this->projector = app(CaseProjector::class);
    $this->rules = app(RuleEngine::class);
});

function kitContents(): string
{
    return file_get_contents(base_path('referencias/kit_publico/events.jsonl'));
}
function kitCase(string $id): CommercialCase
{
    return CommercialCase::where('external_id', $id)->firstOrFail();
}
function candidate(array $overrides = []): array
{
    return array_replace(['action' => 'escalate', 'action_order' => array_values(array_unique([$overrides['action'] ?? 'escalate', ...RuleEngine::ACTIONS])), 'timing' => 'within_24h', 'scheduled_for' => null, 'signals' => [['kind' => 'question', 'event_id' => 'P01-e2', 'quote' => '¿La propuesta incluye instalación?']], 'missing_information' => ['quotation_details'], 'confidence' => 'medium', 'risk_level' => 'medium', 'requires_human_review' => true], $overrides);
}

it('imports the immutable official kit without losing duplicates, section or pre-opening contact', function () {
    $batch = $this->import->import($this->set, kitContents(), 'events.jsonl');
    expect($batch->accepted)->toBe(15)->and($batch->duplicates)->toBe(1)->and($batch->errors)->toBe([]);
    expect(CaseEvent::count())->toBe(15)->and(CommercialCase::count())->toBe(6);
    expect(CaseEvent::where('event_id', 'P02-e2')->first()->data['section'])->toBe('price');
    $contact = CaseEvent::where('event_id', 'P06-e2')->first();
    expect($contact->original['data']['action'])->toBe('contact')->and($contact->data['action_type'])->toBe('contact');
    $s = $this->projector->snapshot(kitCase('P06'));
    expect($s['metrics']['hours_since_last_contact'])->toBe(2.0)->and($this->rules->assess($s)['checks']['R4']['blocked'])->toBeTrue();
    $again = $this->import->import($this->set, kitContents(), 'events.jsonl');
    expect($again->accepted)->toBe(0)->and($again->duplicates)->toBe(16)->and(CaseEvent::count())->toBe(15);
});

it('keeps snapshots immutable and invalidates P06 only after new knowledge is imported', function () {
    $this->import->import($this->set, kitContents(), 'events');
    $service = app(EvaluationService::class);
    $evaluation = $service->request(kitCase('P06'));
    $service->run($evaluation->id);
    expect($evaluation->fresh()->status)->toBe('completed');
    expect($service->current(kitCase('P06'))->result['action'])->toBe('wait');
    $before = $evaluation->snapshot;
    $this->import->import($this->set, file_get_contents(base_path('referencias/kit_publico/updates.jsonl')), 'updates');
    $after = $this->projector->snapshot(kitCase('P06'));
    expect($after['evaluation_at'])->toBe($before['evaluation_at'])->and($after['metrics']['new_message_after_contact'])->toBeTrue();
    expect($this->rules->assess($after)['checks']['R4']['blocked'])->toBeFalse()->and($service->current(kitCase('P06')))->toBeNull();
    expect($evaluation->fresh()->snapshot)->toBe($before);
});

it('enforces boundaries and message exceptions without lifting other rules', function (string $at, bool $blocked) {
    $this->import->import($this->set, kitContents(), 'events');
    $s = $this->projector->snapshot(kitCase('P06'), Utc::parse($at));
    expect($this->rules->assess($s)['checks']['R4']['blocked'])->toBe($blocked);
    $s['metrics']['new_message_after_contact'] = true;
    $s['state']['contact_allowed'] = false;
    $r = $this->rules->assess($s);
    expect($r['checks']['R4']['blocked'])->toBeFalse()->and($r['allowed'])->not->toContain('contact', 'request_information', 'resolve_objection');
})->with([['2026-10-10T13:59:59Z', true], ['2026-10-10T14:00:00Z', false]]);

it('quarantines conflicting IDs without overwriting evidence and still imports other cases', function () {
    $this->import->import($this->set, kitContents(), 'events');
    $changed = str_replace('150000', '200000', kitContents());
    $batch = $this->import->import($this->set, $changed, 'conflict');
    expect(count($batch->errors))->toBe(6)->and(CaseEvent::count())->toBe(15);
    expect(CaseEvent::where('event_id', 'P01-e1')->first()->original['data']['proposal_amount_mxn'])->toBe(150000);
    expect($this->projector->snapshot(kitCase('P01'))['integrity_blocked'])->toBeTrue();
});

it('rejects invalid dates and incomplete patches and preserves previous restrictions', function () {
    $contents = [
        ['event_id' => 'a', 'case_id' => 'C', 'event_type' => 'case.opened', 'occurred_at' => '2026-10-09T13:00:00Z', 'data' => ['status' => 'open', 'proposal_amount_mxn' => 1]],
        ['event_id' => 'b', 'case_id' => 'C', 'event_type' => 'constraint.updated', 'occurred_at' => '2026-10-09T14:00:00Z', 'data' => ['contact_allowed' => false]],
        ['event_id' => 'c', 'case_id' => 'C', 'event_type' => 'constraint.updated', 'occurred_at' => '2026-10-09T15:00:00Z', 'data' => ['contact_not_before' => '2026-10-12T15:00:00Z']],
    ];
    $this->import->import($this->set, implode("\n", array_map('json_encode', $contents)), 'patches');
    $s = $this->projector->snapshot(kitCase('C'));
    expect($s['state']['contact_allowed'])->toBeFalse()->and($s['state']['contact_not_before'])->toBe('2026-10-12T15:00:00Z');
    expect(fn () => Utc::parse('2026-02-30T16:00:00Z'))->toThrow(InvalidArgumentException::class);
});

it('validates literal evidence, ownership, candidate fields and blocked alternatives', function () {
    $this->import->import($this->set, kitContents(), 'events');
    $validator = app(DecisionValidator::class);
    $s = $this->projector->snapshot(kitCase('P01'));
    $compiled = $validator->compile($s, candidate());
    expect(count($compiled['result']))->toBe(11)->and($compiled['result']['action'])->toBe('escalate');
    expect(fn () => $validator->compile($s, candidate(['signals' => [['kind' => 'question', 'event_id' => 'P01-e2', 'quote' => 'Hay stock']]])))->toThrow(InvalidArgumentException::class);
    expect(fn () => $validator->compile($s, candidate(['signals' => [['kind' => 'question', 'event_id' => 'P06-e3', 'quote' => 'fecha']]])))->toThrow(InvalidArgumentException::class);
    expect(fn () => $validator->compile($s, candidate(['probability' => 0.99])))->toThrow(InvalidArgumentException::class);
    $s['state']['contact_allowed'] = false;
    expect(fn () => $validator->compile($s, candidate(['action' => 'resolve_objection', 'missing_information' => []])))->toThrow(InvalidArgumentException::class);
});

it('publishes only no_action for a terminal case without spending', function () {
    config(['integrations.openai.api_key' => 'test-key']);
    $this->import->import($this->set, kitContents(), 'events');
    $service = app(EvaluationService::class);
    $eval = $service->request(kitCase('P05'));
    $service->run($eval->id);
    expect($eval->fresh()->status)->toBe('completed')->and($service->current(kitCase('P05'))->result['action'])->toBe('no_action');
    Http::assertNothingSent();
    expect(AiUsage::count())->toBe(0);
});

it('calls the provider once, settles usage and records an auditable structured result', function () {
    config(['integrations.openai.api_key' => 'test-key']);
    Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'model' => 'gpt-5.6-luna', 'usage' => ['input_tokens' => 1500, 'output_tokens' => 300], 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(candidate())]]]]])]);
    $this->import->import($this->set, kitContents(), 'events');
    $service = app(EvaluationService::class);
    $eval = $service->request(kitCase('P01'));
    $service->run($eval->id);
    $service->run($eval->id);
    expect($eval->fresh()->status)->toBe('completed')->and(AiUsage::first()->status)->toBe('confirmed');
    expect(Recommendation::first()->publication_status)->toBe('awaiting_review');
    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => $r['store'] === false && $r['text']['format']['strict'] === true && $r['reasoning']['effort'] === 'none');
});

it('pauses before an unaffordable call and keeps uncertain costs reserved', function () {
    config(['integrations.openai.api_key' => 'test-key', 'byecode.budget.daily_mxn' => 0]);
    $this->import->import($this->set, kitContents(), 'events');
    $service = app(EvaluationService::class);
    $eval = $service->request(kitCase('P01'));
    $service->run($eval->id);
    expect($eval->fresh()->error_code)->toBe('budget_exhausted');
    Http::assertNothingSent();
    config(['byecode.budget.daily_mxn' => 22]);
    $eval2 = $service->request(kitCase('P04'));
    Http::fake(['api.openai.com/*' => Http::response(['error' => 'unavailable'], 503)]);
    $service->run($eval2->id);
    expect(AiUsage::first()->status)->toBe('uncertain')->and(app(BudgetLedger::class)->summary()['daily_micromxn'])->toBeGreaterThan(0);
    expect(Recommendation::count())->toBe(0);
});

it('does not pretend missing credentials are a commercial no_action', function () {
    $this->import->import($this->set, kitContents(), 'events');
    $service = app(EvaluationService::class);
    $eval = $service->request(kitCase('P01'));
    $service->run($eval->id);
    expect($eval->fresh()->status)->toBe('blocked')->and($eval->fresh()->error_code)->toBe('missing_api_key')->and(Recommendation::count())->toBe(0);
});
