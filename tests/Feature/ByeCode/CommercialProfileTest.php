<?php

use App\Domain\Cases\CommercialProfile;
use App\Domain\Cases\CustomerJourney;
use App\Domain\Cases\CustomerProfile;
use App\Domain\Cases\Workspace;
use App\Models\CommercialCase;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config(['integrations.openai.api_key' => null, 'inertia.ssr.enabled' => false]);
    $this->seller = User::factory()->create(['role' => 'commerce']);
    $this->buyer = User::factory()->create(['role' => 'customer']);
    $this->customer = Customer::create(['user_id' => $this->seller->id, 'account_id' => $this->buyer->id, 'company' => 'Negocio individual de prueba']);
    $this->corporate = Customer::create(['user_id' => $this->seller->id, 'company' => 'Cadena de prueba']);
    $set = app(Workspace::class)->live($this->seller);
    foreach ([$this->customer, $this->corporate] as $customer) {
        $case = CommercialCase::create(['data_set_id' => $set->id, 'customer_id' => $customer->id, 'external_id' => 'TEST-'.$customer->id, 'title' => 'Pedido inicial', 'amount_cents' => 5000000]);
        app(CustomerJourney::class)->record($case, $this->buyer, 'message.received', ['message' => $customer->id === $this->customer->id
            ? 'Yo decido las compras. Opero solo y revendo las prendas por pedido.'
            : 'Somos una cadena; compras requiere alta de proveedor, muestras y entregas programadas.']);
        DB::table('sale_records')->insert(['event_id' => (string) Str::uuid(), 'commercial_case_id' => $case->id, 'author_id' => $this->seller->id,
            'kind' => 'payment', 'amount_cents' => 5000000, 'reference' => 'Pago sintético', 'occurred_at' => now(), 'created_at' => now()]);
    }
    $this->evidence = DB::table('activity_events')->where('customer_id', $this->customer->id)->value('id');
    $this->corporateEvidence = DB::table('activity_events')->where('customer_id', $this->corporate->id)->value('id');
    $this->fact = ['name' => 'commercial.operating_scale', 'scope' => 'cliente', 'value_json' => '"individual"', 'confidence' => 'high', 'evidence' => [$this->evidence]];
});

it('distinguishes commercial structure for customers with the same paid amount without automatic tiers', function () {
    $profile = app(CustomerProfile::class);
    $beforeRevision = CommercialCase::where('customer_id', $this->customer->id)->value('revision');
    expect($profile->indicators($this->customer)['paid_cents'])->toEqual(5000000)
        ->and($profile->indicators($this->corporate)['paid_cents'])->toEqual(5000000)
        ->and(app(CommercialProfile::class)->summaries([$this->customer->id, $this->corporate->id]))->toBe([]);
    $this->actingAs($this->seller)->post('/customers/'.$this->customer->id.'/facts', $this->fact)->assertRedirect()->assertSessionHasNoErrors();
    $this->post('/customers/'.$this->corporate->id.'/facts', [...$this->fact, 'value_json' => '"corporate_group"', 'evidence' => [$this->corporateEvidence]])->assertRedirect()->assertSessionHasNoErrors();
    $this->get('/customers/'.$this->customer->id)->assertInertia(fn (Assert $p) => $p->where('commercialProfile.operating_scale.value', 'individual')->where('indicators.paid_cents', 5000000)->where('commercialProfile.operating_scale.nature', 'confirmed'));
    $this->get('/customers?operating_scale=corporate_group')->assertInertia(fn (Assert $p) => $p->has('customers.data', 1)->where('customers.data.0.id', $this->corporate->id));
    expect(CommercialCase::where('customer_id', $this->customer->id)->value('revision'))->toBe($beforeRevision + 1);
});

it('filters only current confirmed facts and keeps uncertain classifications visible for review', function () {
    $profile = app(CustomerProfile::class);
    $profile->fact($this->customer, $this->fact, 'inferred', 'fake');
    $this->actingAs($this->seller)->get('/customers?operating_scale=individual')->assertInertia(fn (Assert $p) => $p->has('customers.data', 0));
    $this->get('/customers/'.$this->customer->id)->assertInertia(fn (Assert $p) => $p->where('commercialProfile.operating_scale.nature', 'inferred'));
    $profile->fact($this->customer, $this->fact, 'confirmed');
    $profile->fact($this->customer, [...$this->fact, 'value_json' => '"corporate_group"'], 'inferred', 'fake');
    $this->get('/customers?operating_scale=individual')->assertInertia(fn (Assert $p) => $p->has('customers.data', 1));
    $this->get('/customers?operating_scale=corporate_group')->assertInertia(fn (Assert $p) => $p->has('customers.data', 0));
    DB::table('profile_facts')->where('status', 'active')->update(['valid_until' => now()->subMinute()]);
    $this->get('/customers?operating_scale=individual')->assertInertia(fn (Assert $p) => $p->has('customers.data', 0));
    expect(app(CommercialProfile::class)->summaries([$this->customer->id]))->toBe([]);
});

it('withdraws a classification to unknown without reviving old values or deleting history', function () {
    $profile = app(CustomerProfile::class);
    $profile->fact($this->customer, $this->fact, 'confirmed');
    $this->actingAs($this->seller)->post('/customers/'.$this->customer->id.'/facts', [...$this->fact, 'value_json' => 'null'])->assertRedirect()->assertSessionHasNoErrors();
    $this->get('/customers/'.$this->customer->id)->assertInertia(fn (Assert $p) => $p->where('commercialProfile.operating_scale.value', null)->has('commercialProfile.operating_scale.labels', 0)->has('facts', 2));
    $this->get('/customers?operating_scale=individual')->assertInertia(fn (Assert $p) => $p->has('customers.data', 0));
    expect(DB::table('profile_facts')->where('status', 'superseded')->count())->toBe(1);
});

it('validates canonical dimensions and prevents another customer from supplying evidence', function () {
    $this->actingAs($this->seller);
    foreach (['"vip"', '50000', '["individual"]', '{"amount":50000}'] as $invalid) {
        $this->post('/customers/'.$this->customer->id.'/facts', [...$this->fact, 'value_json' => $invalid])->assertSessionHasErrors('value_json');
    }
    $this->post('/customers/'.$this->customer->id.'/facts', [...$this->fact, 'name' => 'commercial.automatic_credit'])->assertSessionHasErrors('name');
    $this->post('/customers/'.$this->customer->id.'/facts', [...$this->fact, 'scope' => 'pedido 1'])->assertSessionHasErrors('name');
    $this->post('/customers/'.$this->customer->id.'/facts', [...$this->fact, 'evidence' => [$this->corporateEvidence]])->assertSessionHasErrors('evidence');
    expect(DB::table('profile_facts')->count())->toBe(0);
});

it('combines requirements with other dimensions while preserving unique customer needs', function () {
    $profile = app(CustomerProfile::class);
    $profile->fact($this->customer, $this->fact, 'confirmed');
    $profile->fact($this->customer, [...$this->fact, 'name' => 'commercial.service_requirements', 'value_json' => '["samples","custom_packaging"]'], 'confirmed');
    $profile->fact($this->customer, [...$this->fact, 'name' => 'Empaque exclusivo', 'scope' => 'colección de invierno', 'value_json' => '"Papel con marca propia"'], 'confirmed');
    $this->actingAs($this->seller)->get('/customers?operating_scale=individual&service_requirements=samples')->assertInertia(fn (Assert $p) => $p->has('customers.data', 1)->where('customers.data.0.commercialProfile.service_requirements.labels.0', 'Muestras y aprobación previa'));
    $this->get('/customers?operating_scale=corporate_group&service_requirements=samples')->assertInertia(fn (Assert $p) => $p->has('customers.data', 0));
    $this->post('/customers/'.$this->customer->id.'/facts', [...$this->fact, 'name' => 'commercial.service_requirements', 'value_json' => '["samples","samples"]'])->assertSessionHasErrors('value_json');
    expect(DB::table('profile_facts')->where('name', 'Empaque exclusivo')->value('scope'))->toBe('colección de invierno');
});

it('restricts classification to commerce while allowing admin read only and hiding it from customers', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get('/customers/'.$this->customer->id)->assertInertia(fn (Assert $p) => $p->where('readOnly', true));
    $this->post('/customers/'.$this->customer->id.'/facts', $this->fact)->assertForbidden();
    $this->actingAs($this->buyer)->get('/customers/'.$this->customer->id)->assertForbidden();
    $this->post('/customers/'.$this->customer->id.'/facts', $this->fact)->assertForbidden();
});

it('paginates profiles and searches without guessing classifications from company names', function () {
    foreach (range(1, 25) as $i) {
        Customer::create(['user_id' => $this->seller->id, 'company' => 'Empresa de prueba '.$i]);
    }
    $this->actingAs($this->seller)->get('/customers')->assertInertia(fn (Assert $p) => $p->has('customers.data', 24)->where('customers.total', 27)->where('customers.last_page', 2));
    $this->get('/customers?search=CADENA')->assertInertia(fn (Assert $p) => $p->has('customers.data', 1)->where('customers.data.0.id', $this->corporate->id)->has('customers.data.0.commercialProfile', 0));
    $this->getJson('/customers?operating_scale=vip')->assertUnprocessable();
});

it('uses the commercial vocabulary for AI hypotheses and rejects unsupported categories', function () {
    config(['integrations.openai.api_key' => 'fake-test-key']);
    $response = fn ($fact) => ['status' => 'completed', 'usage' => ['input_tokens' => 100, 'output_tokens' => 60], 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['facts' => [$fact]])]]]]];
    Http::fake(['api.openai.com/*' => Http::sequence()->push($response($this->fact))->push($response([...$this->fact, 'value_json' => '"vip"']))]);
    $profile = app(CustomerProfile::class);
    $profile->process($this->customer);
    expect(DB::table('profile_facts')->first()->nature)->toBe('inferred');
    Http::assertSent(fn ($request) => str_contains($request['instructions'], 'commercial.operating_scale') && str_contains($request['instructions'], 'solo por nombre de marca'));
    $case = CommercialCase::where('customer_id', $this->customer->id)->firstOrFail();
    app(CustomerJourney::class)->record($case, $this->buyer, 'message.received', ['message' => 'Otra conversación']);
    $profile->process($this->customer);
    expect(DB::table('profile_facts')->count())->toBe(1)->and(DB::table('profile_runs')->latest('id')->first()->status)->toBe('failed');
});
