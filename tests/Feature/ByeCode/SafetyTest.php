<?php

use App\Domain\Cases\CaseProjector;
use App\Domain\Decision\DecisionValidator;
use App\Domain\Evaluation\BudgetLedger;
use App\Domain\Evaluation\EvaluationService;
use App\Domain\Events\ImportService;
use App\Models\AiUsage;
use App\Models\CommercialCase;
use App\Models\DataSet;
use App\Models\Evaluation;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);
beforeEach(function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    config(['integrations.openai.api_key' => 'test-only-key', 'inertia.ssr.enabled' => false]);
    $this->set = DataSet::create(['user_id' => User::factory()->create()->id, 'mode' => 'kit', 'name' => 'Safety fixtures', 'evaluation_at' => '2026-10-09T16:00:00Z']);
    app(ImportService::class)->import($this->set, file_get_contents(base_path('referencias/kit_publico/events.jsonl')), 'Kit');
});

it('blocks every buyer-facing action under each hard restriction', function (string $action, string $rule) {
    $case = CommercialCase::where('external_id', 'P01')->firstOrFail();
    $s = app(CaseProjector::class)->snapshot($case);
    match ($rule) {
        'R1' => $s['state']['status'] = 'accepted',
        'R2' => $s['state']['contact_allowed'] = false,
        'R3' => $s['state']['contact_not_before'] = '2026-10-10T16:00:00Z',
        'R4' => $s['metrics'] = array_replace($s['metrics'], ['last_contact_at' => '2026-10-09T15:00:00Z', 'new_message_after_contact' => false]),
    };
    $r = ['case_id' => 'P01', 'action' => $action, 'timing' => 'at_date', 'scheduled_for' => '2026-10-11T16:00:00Z', 'reason' => 'Intención de contacto futuro', 'evidence_event_ids' => ['P01-e2'], 'confidence' => 'high', 'confidence_reason' => 'Prueba', 'risk_level' => 'low', 'requires_human_review' => true, 'missing_information' => []];
    expect(fn () => app(DecisionValidator::class)->validate($s, $r))->toThrow(InvalidArgumentException::class);
})->with(['contact', 'request_information', 'resolve_objection'])->with(['R1', 'R2', 'R3', 'R4']);

it('accounts for refusal incomplete malformed and failed provider responses without publishing', function (array $response, int $status) {
    Http::fake(['api.openai.com/*' => Http::response($response, $status)]);
    $case = CommercialCase::where('external_id', 'P01')->firstOrFail();
    $s = app(EvaluationService::class);
    $e = $s->request($case);
    $s->run($e->id);
    expect($e->fresh()->status)->toBe('failed')->and(Recommendation::count())->toBe(0)->and(AiUsage::count())->toBe(1);
    Http::assertSentCount(1);
})->with([
    [['status' => 'completed', 'output' => [['content' => [['type' => 'refusal', 'refusal' => 'Cannot comply']]]]], 200],
    [['status' => 'incomplete', 'usage' => ['input_tokens' => 100, 'output_tokens' => 1800], 'output' => []], 200],
    [['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => '{bad}']]]]], 200],
    [['error' => 'rate limit'], 429],
]);

it('reuses unchanged semantic evidence across additional views without another charge', function () {
    $candidate = ['action' => 'escalate', 'timing' => 'within_24h', 'scheduled_for' => null, 'signals' => [['kind' => 'question', 'event_id' => 'P01-e2', 'quote' => '¿La propuesta incluye instalación?']], 'missing_information' => ['quotation_details'], 'confidence' => 'medium', 'risk_level' => 'medium', 'requires_human_review' => true];
    Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'model' => config('byecode.model'), 'usage' => ['input_tokens' => 1500, 'output_tokens' => 300], 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($candidate)]]]]])]);
    $case = CommercialCase::where('external_id', 'P01')->firstOrFail();
    $s = app(EvaluationService::class);
    $e = $s->request($case);
    $s->run($e->id);
    app(ImportService::class)->import($this->set, json_encode(['event_id' => 'extra-view', 'case_id' => 'P01', 'event_type' => 'proposal.viewed', 'occurred_at' => '2026-10-09T15:30:00Z', 'data' => ['section' => 'summary']]), 'Extra view');
    $case->refresh();
    $e2 = $s->request($case);
    $s->run($e2->id);
    expect($s->current($case)->source)->toBe('openai_cache')->and(AiUsage::count())->toBe(1);
    Http::assertSentCount(1);
});

it('rejects a response if the case changes while HTTP is in flight', function () {
    $candidate = ['action' => 'escalate', 'timing' => 'within_24h', 'scheduled_for' => null, 'signals' => [['kind' => 'question', 'event_id' => 'P01-e2', 'quote' => '¿La propuesta incluye instalación?']], 'missing_information' => ['quotation_details'], 'confidence' => 'medium', 'risk_level' => 'medium', 'requires_human_review' => true];
    Http::fake(function () use ($candidate) {
        CommercialCase::where('external_id', 'P01')->increment('revision');

        return Http::response(['status' => 'completed', 'model' => config('byecode.model'), 'usage' => ['input_tokens' => 1500, 'output_tokens' => 300], 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($candidate)]]]]]);
    });
    $case = CommercialCase::where('external_id', 'P01')->firstOrFail();
    $s = app(EvaluationService::class);
    $e = $s->request($case);
    $s->run($e->id);
    expect($e->fresh()->status)->toBe('stale')->and(Recommendation::count())->toBe(0)->and(AiUsage::first()->status)->toBe('confirmed');
});

it('does not export partial coverage or spend by running the benchmark without opt-in', function () {
    $user = User::findOrFail($this->set->user_id);
    $this->actingAs($user)->get('/exports/'.$this->set->id.'/recommendations')->assertStatus(422);
    $this->artisan('byecode:evaluate-model', ['--user' => $user->id])->assertFailed();
    Http::assertNothingSent();
});

it('retains the global budget when its owning account is deleted', function (bool $confirmed) {
    $case = CommercialCase::where('external_id', 'P01')->firstOrFail();
    $evaluation = app(EvaluationService::class)->request($case);
    $ledger = app(BudgetLedger::class);
    $usage = $ledger->reserve($evaluation, 3000);
    if ($confirmed) {
        $ledger->settle($usage, 1000, 200, 'test-request');
    }
    $before = $ledger->summary();
    // The new FK blocks account deletion while business history exists.
    expect(fn () => User::findOrFail($this->set->user_id)->delete())->toThrow(QueryException::class);
    $this->set->delete();
    User::findOrFail($this->set->user_id)->delete();
    expect($usage->fresh()->evaluation_id)->toBeNull()
        ->and(AiUsage::count())->toBe(1)
        ->and($ledger->summary())->toBe($before)
        ->and(Evaluation::count())->toBe(0);
    Http::assertNothingSent();
})->with([false, true]);
