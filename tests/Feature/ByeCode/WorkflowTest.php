<?php

use App\Domain\Cases\QuoteService;
use App\Domain\Evaluation\EvaluationService;
use App\Domain\Events\ImportService;
use App\Models\CaseEvent;
use App\Models\Customer;
use App\Models\DataSet;
use App\Models\Product;
use App\Models\QuoteVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    config(['integrations.openai.api_key' => null]);
    config(['inertia.ssr.enabled' => false]);
    $this->owner = User::factory()->create();
    $this->customer = Customer::create(['user_id' => $this->owner->id, 'company' => 'Cliente de prueba']);
    $this->product = Product::create(['user_id' => $this->owner->id, 'sku' => 'TEST-M', 'name' => 'Playera de prueba', 'category' => 'blank', 'moq' => 20, 'unit_price_cents' => 12550]);
    $this->input = ['customer_id' => $this->customer->id, 'title' => 'Pedido de prueba', 'valid_until' => now()->addDays(7)->format('Y-m-d'), 'terms' => 'Pendiente de confirmar entrega', 'authorization_reference' => 'Referencia de prueba', 'tax' => '16.01', 'shipping' => '20.20', 'discount' => '5.15',
        'lines' => [['product_id' => $this->product->id, 'quantity' => 10, 'unit_price' => '125.50']]];
    $this->case = app(QuoteService::class)->save($this->owner, $this->input);
});

it('calculates exact totals and keeps prior quotation versions immutable', function () {
    $q = QuoteVersion::first();
    expect($q->total_cents)->toBe(128606)->and($q->lines[0]['below_moq'])->toBeTrue()->and($q->lines[0])->not->toHaveKey('supply_mode');
    app(QuoteService::class)->save($this->owner, [...$this->input, 'expected_revision' => $this->case->revision, 'lines' => [['product_id' => $this->product->id, 'quantity' => 12, 'unit_price' => '140.10']]], $this->case);
    expect(QuoteVersion::count())->toBe(2)->and($q->fresh()->total_cents)->toBe(128606)->and(QuoteVersion::latest('version')->first()->lines[0]['quantity'])->toBe(12);
    expect(fn () => app(QuoteService::class)->save($this->owner, [...$this->input, 'expected_revision' => 1], $this->case))->toThrow(ValidationException::class);
});

it('rejects invalid quantities and prices without creating partial quotations', function () {
    expect(fn () => app(QuoteService::class)->save($this->owner, [...$this->input, 'lines' => [['product_id' => $this->product->id, 'quantity' => 0, 'unit_price' => '125.50']]]))->toThrow(ValidationException::class);
    expect(fn () => app(QuoteService::class)->save($this->owner, [...$this->input, 'lines' => [['product_id' => $this->product->id, 'quantity' => 10, 'unit_price' => '-1']]]))->toThrow(ValidationException::class);
    expect(QuoteVersion::count())->toBe(1);
});

it('shares commercial data while keeping imported sets private', function () {
    $other = User::factory()->create();
    $this->actingAs($other)->get('/cases/'.$this->case->id)->assertOk();
    $this->get('/customers')->assertInertia(fn (Assert $page) => $page->component('journey/customers')->has('customers.data', 1)->where('customers.total', 1));
    $this->get('/exports/'.$this->case->data_set_id.'/recommendations')->assertNotFound();
    $buyer = User::factory()->create(['role' => 'customer']);
    $this->actingAs($buyer)->get('/cases/'.$this->case->id)->assertForbidden();
});

it('requires account ownership for old private links', function () {
    $token = Str::random(64);
    $this->case->update(['portal_token_hash' => hash('sha256', $token), 'portal_expires_at' => now()->addDay()]);
    $this->get('/portal/'.$token)->assertRedirect('/login');
    $buyer = User::factory()->create(['role' => 'customer']);
    $this->actingAs($buyer)->get('/portal/'.$token)->assertNotFound();
    $this->customer->update(['account_id' => $buyer->id]);
    $this->get('/portal/'.$token)->assertRedirect('/my/quotes/'.$this->case->public_id)->assertHeader('Referrer-Policy', 'no-referrer');
    $this->case->update(['portal_expires_at' => now()->subSecond()]);
    $this->get('/portal/'.$token)->assertNotFound();
});

it('validates manual forms before ingesting and requires an execution reference', function () {
    $this->actingAs($this->owner)->post('/cases/'.$this->case->id.'/events', ['type' => 'action.executed', 'action' => 'contact', 'expected_revision' => $this->case->revision, 'confirmed' => true])->assertSessionHasErrors('execution_reference');
    expect($this->case->fresh()->integrity_blocked)->toBeFalse();
    $this->post('/cases/'.$this->case->id.'/events', ['type' => 'constraint.updated', 'contact_allowed' => false, 'contact_not_before' => '2026-12-01', 'expected_revision' => $this->case->revision, 'confirmed' => true])->assertSessionHasErrors('contact_not_before');
});

it('never treats approval as an executed contact and rejects stale reviews', function () {
    $service = app(EvaluationService::class);
    $evaluation = $service->request($this->case);
    $service->run($evaluation->id);
    $r = $service->current($this->case);
    $r->update(['publication_status' => 'awaiting_review']);
    $this->actingAs($this->owner)->post('/recommendations/'.$r->id.'/review', ['decision' => 'rejected', 'reason' => 'Revisar antes de contactar'])->assertRedirect();
    expect($r->fresh()->publication_status)->toBe('rejected')->and($service->current($this->case))->toBeNull()->and(CaseEvent::where('event_type', 'action.executed')->count())->toBe(0);
    $this->post('/recommendations/'.$r->id.'/review', ['decision' => 'approved', 'reason' => 'Segundo intento inválido'])->assertConflict();
});

it('bounds explicit retries and returns an already pending attempt', function () {
    app(QuoteService::class)->event($this->case, 'message.received', ['message' => '¿Cuál es la composición?']);
    $case = $this->case->fresh();
    $service = app(EvaluationService::class);
    $e = $service->request($case);
    $service->run($e->id);
    expect($e->fresh()->status)->toBe('blocked');
    $retry = $service->request($case, true);
    expect($service->request($case, true)->id)->toBe($retry->id);
    $service->run($retry->id);
    $third = $service->request($case, true);
    $service->run($third->id);
    expect(fn () => $service->request($case, true))->toThrow(ValidationException::class);
    Http::assertNothingSent();
});

it('renders each workspace screen with owner-scoped data', function (string $path) {
    $this->actingAs($this->owner)->get($path)->assertOk();
})->with(['/workspace', '/cases', '/quotes/create', '/customers', '/products', '/imports', '/intelligence', '/prediction']);

it('expires a recommendation when a known future constraint becomes effective', function () {
    $this->freezeTime();
    $set = DataSet::find($this->case->data_set_id);
    app(ImportService::class)->import($set, json_encode(['event_id' => 'future', 'case_id' => $this->case->external_id, 'event_type' => 'constraint.updated', 'occurred_at' => now()->addMinutes(5)->format('Y-m-d\TH:i:s\Z'), 'data' => ['contact_allowed' => false]]), 'Future constraint');
    $case = $this->case->fresh();
    $service = app(EvaluationService::class);
    $e = $service->request($case);
    $service->run($e->id);
    expect($service->current($case))->not->toBeNull();
    $this->travel(6)->minutes();
    expect($service->current($case))->toBeNull();
    $next = $service->request($case);
    expect($next->id)->not->toBe($e->id);
    $service->run($next->id);
    expect($service->current($case)->result['action'])->toBe('no_action');
});
