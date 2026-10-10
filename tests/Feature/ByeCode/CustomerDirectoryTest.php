<?php

use App\Domain\Cases\CustomerJourney;
use App\Domain\Cases\CustomerProfile;
use App\Domain\Cases\Workspace;
use App\Models\CommercialCase;
use App\Models\Customer;
use App\Models\User;
use Carbon\CarbonImmutable;
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
    $this->customer = Customer::create(['user_id' => $this->seller->id, 'account_id' => $this->buyer->id, 'company' => 'Cliente con historial']);
    $this->emptyCustomer = Customer::create(['user_id' => $this->seller->id, 'company' => 'Cliente sin actividad']);
    $set = app(Workspace::class)->live($this->seller);
    $this->cases = collect([1, 2])->map(fn ($i) => CommercialCase::create(['data_set_id' => $set->id, 'customer_id' => $this->customer->id, 'external_id' => 'CRM-'.$i, 'title' => 'Solicitud '.$i]));
    foreach ($this->cases as $case) {
        DB::table('purchase_requests')->insert(['account_id' => $this->buyer->id, 'commercial_case_id' => $case->id, 'submission_id' => (string) Str::uuid(), 'lines' => '[]', 'source' => 'self_service', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['payment', 15000], ['payment', 5000], ['formalized', 30000]] as [$kind, $amount]) {
            DB::table('sale_records')->insert(['event_id' => (string) Str::uuid(), 'commercial_case_id' => $case->id, 'author_id' => $this->seller->id, 'kind' => $kind, 'amount_cents' => $amount, 'reference' => 'Prueba CRM', 'occurred_at' => now(), 'created_at' => now()]);
        }
        app(CustomerJourney::class)->record($case, $this->buyer, 'message.received', ['message' => 'Necesitamos muestras antes de confirmar.']);
    }
    $this->evidence = DB::table('activity_events')->where('customer_id', $this->customer->id)->value('id');
});

it('aggregates requests and payments once and agrees with the full customer profile', function () {
    $expected = app(CustomerProfile::class)->indicators($this->customer);
    $lastActivity = CarbonImmutable::parse($expected['last_activity'], 'UTC')->toIso8601ZuluString();
    $this->actingAs($this->seller)->get('/customers')->assertInertia(fn (Assert $page) => $page
        ->where('customers.data.0.requests_count', 2)->where('customers.data.0.paid_cents', 40000)->where('customers.data.0.formalized_cents', 60000)
        ->where('customers.data.0.paid_cents', $expected['paid_cents'])
        ->where('customers.data.0.last_activity', $lastActivity)
        ->where('customers.data.1.requests_count', 0)->where('customers.data.1.paid_cents', 0)->where('customers.data.1.last_activity', null)
        ->where('customerPreview', null));
    $this->get('/customers/'.$this->customer->id)->assertInertia(fn (Assert $page) => $page->where('indicators.last_activity', $lastActivity));
});

it('sorts the whole directory before pagination with stable ties and null activities last', function () {
    for ($i = 0; $i < 25; $i++) {
        Customer::create(['user_id' => $this->seller->id, 'company' => 'A sin actividad '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
    }
    $this->actingAs($this->seller)->get('/customers?sort=activity')->assertInertia(fn (Assert $page) => $page
        ->where('customers.data.0.id', $this->customer->id)->where('customers.data.1.id', $this->emptyCustomer->id)->where('customers.total', 27)->has('customers.data', 24));
    $this->get('/customers?sort=paid')->assertInertia(fn (Assert $page) => $page->where('customers.data.0.id', $this->customer->id)->where('customers.data.1.id', $this->emptyCustomer->id));
    $this->get('/customers?sort=name')->assertInertia(fn (Assert $page) => $page->where('customers.data.0.company', 'A sin actividad 00'));
    $this->get('/customers?sort=paid&page=2')->assertInertia(fn (Assert $page) => $page->has('customers.data', 3)->where('filters.sort', 'paid')->where('customers.current_page', 2));
});

it('combines confirmed filters with ordering and returns a read only preview independently', function () {
    app(CustomerProfile::class)->fact($this->customer, ['name' => 'commercial.operating_scale', 'scope' => 'cliente', 'value_json' => '"individual"', 'confidence' => 'high', 'evidence' => [$this->evidence]], 'confirmed');
    $this->actingAs($this->seller)->get('/customers?sort=paid&operating_scale=individual&preview='.$this->customer->id)->assertInertia(fn (Assert $page) => $page
        ->has('customers.data', 1)->where('customerPreview.id', $this->customer->id)->where('customerPreview.paid_cents', 40000)
        ->where('customerPreview.commercialProfile.operating_scale.value', 'individual')->missing('filters.preview'));
    Http::assertNothingSent();
});

it('validates directory parameters and preserves access boundaries', function () {
    $this->actingAs($this->seller)->get('/customers?sort=arbitrary')->assertSessionHasErrors('sort');
    $this->get('/customers?preview=bad')->assertSessionHasErrors('preview');
    $this->get('/customers?preview=999999')->assertNotFound();
    $this->actingAs($this->buyer)->get('/customers?preview='.$this->customer->id)->assertForbidden();
    $this->get('/customers/'.$this->customer->id.'?tab=activity&event='.$this->evidence)->assertForbidden();
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get('/customers/'.$this->customer->id.'?tab=activity')->assertInertia(fn (Assert $page) => $page->where('readOnly', true)->where('activeTab', 'activity'));
    $this->get('/customers?preview='.$this->customer->id)->assertForbidden();
});

it('opens old evidence beyond the recent window without admitting another customers events', function () {
    for ($i = 0; $i < 201; $i++) {
        app(CustomerJourney::class)->record($this->cases->first(), $this->buyer, 'message.received', ['message' => 'Evento posterior '.$i]);
    }
    $this->actingAs($this->seller)->get('/customers/'.$this->customer->id.'?event='.$this->evidence)->assertInertia(fn (Assert $page) => $page
        ->has('events', 200)->where('selectedEvent.id', $this->evidence)->where('activeTab', 'activity')
        ->where('events', fn ($events) => ! collect($events)->contains('id', $this->evidence)));
    $this->get('/customers/'.$this->emptyCustomer->id.'?event='.$this->evidence)->assertNotFound();
    $this->get('/customers/'.$this->customer->id.'?tab=invalid')->assertSessionHasErrors('tab');
});

it('preserves the directory context across profile sections using only local filter parameters', function () {
    $query = http_build_query(['search' => 'Cliente', 'sort' => 'paid', 'page' => '2', 'preview' => '1', 'redirect' => 'https://example.test']);
    $this->actingAs($this->seller)->get('/customers/'.$this->customer->id.'?tab=opportunities&directory='.urlencode($query))
        ->assertInertia(fn (Assert $page) => $page->where('activeTab', 'opportunities')->where('directoryQuery', 'search=Cliente&sort=paid&page=2'));
});
