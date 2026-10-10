<?php

use App\Domain\Cases\ConversationService;
use App\Domain\Cases\CustomerJourney;
use App\Domain\Cases\CustomerProfile;
use App\Domain\Demo\DemoIntegrityAudit;
use App\Domain\Demo\DemoPostgresLoader;
use App\Domain\Demo\DemoScenarioLoader;
use App\Domain\Evaluation\EvaluationService;
use App\Domain\Prediction\BusinessHistory;
use App\Jobs\DeliverTransactionalMail;
use App\Models\CommercialCase;
use App\Models\Customer;
use App\Models\Product;
use App\Models\QuoteVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['demo.enabled' => true, 'demo.active_key' => DemoScenarioLoader::KEY, 'demo.customer_password' => 'FixturePassword123!',
        'integrations.openai.api_key' => 'test-key-must-never-be-used', 'onboarding.mail_enabled' => true, 'inertia.ssr.enabled' => false]);
    Http::preventStrayRequests();
    Mail::fake();
    Storage::fake('local');
    foreach (['jorge', 'dani', 'jesus', 'sau'] as $name) {
        User::factory()->create(['email' => $name.'@gmail.com', 'role' => 'commerce']);
    }
});

it('replays a coherent history and preserves subsequent work on rerun', function () {
    $report = app(DemoScenarioLoader::class)->load();
    expect($report['counts'])->toMatchArray(['customers' => 12, 'crm' => 10, 'cases' => 34, 'quotes' => 46, 'payments' => 27, 'attachments' => 6]);
    expect(DB::table('evaluations')->where('status', 'failed')->pluck('error_code')->all())->toBe([]);
    expect(DB::table('jobs')->count())->toBe(0);
    $daniela = User::where('email', 'daniela@gmail.com')->firstOrFail();
    expect(Hash::check('FixturePassword123!', $daniela->password))->toBeTrue();
    expect(Customer::where('account_id', $daniela->id)->exists())->toBeFalse();
    $case = app(CustomerJourney::class)->submit($daniela, ['submission_id' => (string) Str::uuid(), 'company' => 'Nuevo proyecto de Daniela',
        'lines' => [['product_id' => Product::firstOrFail()->id, 'quantity' => 24]]]);
    expect($case->demo_run_id)->toBe($report['run_id'])->and(Customer::findOrFail($case->customer_id)->demo_run_id)->toBe($report['run_id']);
    $case->update(['title' => 'Avance que se debe conservar']);
    $daniela->forceFill(['password' => 'ChangedByCustomer123!'])->save();
    $rerun = app(DemoScenarioLoader::class)->load();
    expect($rerun['counts']['cases'])->toBe(35)->and($case->fresh()->title)->toBe('Avance que se debe conservar');
    expect(Hash::check('ChangedByCustomer123!', $daniela->fresh()->password))->toBeTrue();
    Http::assertNothingSent();
    Mail::assertNothingSent();
});

it('keeps demo email and AI suppressed after the loader exits', function () {
    $report = app(DemoScenarioLoader::class)->load();
    config(['demo.enabled' => false, 'demo.active_key' => null]);
    $buyer = User::where('email', 'marina@gmail.com')->firstOrFail();
    $buyer->sendPasswordResetNotification('not-a-real-token');
    $mail = DB::table('mail_deliveries')->latest('id')->first();
    expect($mail->status)->toBe('suppressed_demo')->and($mail->encrypted_body)->toBeNull();
    (new DeliverTransactionalMail($mail->id))->handle();
    $case = CommercialCase::where('demo_run_id', $report['run_id'])->where('status', 'open')->firstOrFail();
    app(CustomerProfile::class)->process(Customer::findOrFail($case->customer_id), true);
    $evaluation = app(EvaluationService::class)->request($case, true);
    app(EvaluationService::class)->run($evaluation->id);
    expect($evaluation->fresh()->provider)->toBe('rules')->and(DB::table('ai_usages')->count())->toBe(0);
    $training = app(BusinessHistory::class)->train(User::where('role', 'commerce')->firstOrFail());
    expect($training->status)->toBe('insufficient_data');
    app(DemoIntegrityAudit::class)->verify($report['run_id']);
    Http::assertNothingSent();
    Mail::assertNothingSent();
});

it('blocks commerce contacts under R3 and R4 while allowing the buyer to reply', function () {
    $report = app(DemoScenarioLoader::class)->load();
    foreach (['A04', 'A07', 'A10'] as $key) {
        $id = DB::table('demo_records')->where('demo_run_id', $report['run_id'])->where('logical_key', $key)->value('entity_id');
        $case = CommercialCase::findOrFail($id);
        $seller = User::findOrFail($case->assigned_to);
        $this->actingAs($seller)->post('/journey/'.$case->id.'/messages', ['event_id' => (string) Str::uuid(), 'body' => 'Este contacto debe bloquearse'])->assertStatus(422);
    }
    $buyer = User::findOrFail(Customer::findOrFail($case->customer_id)->account_id);
    app(ConversationService::class)->message($buyer, $case, ['event_id' => (string) Str::uuid(), 'body' => 'Respondo sobre la propuesta y confirmo mi interés.']);
    $this->actingAs($seller)->post('/journey/'.$case->id.'/messages', ['event_id' => (string) Str::uuid(), 'body' => 'Gracias; revisamos las cantidades que solicitas.'])->assertRedirect()->assertSessionHasNoErrors();
});

it('refuses email collisions without modifying existing accounts or partial data', function () {
    $existing = User::factory()->create(['email' => 'marina@gmail.com', 'role' => 'admin']);
    expect(fn () => app(DemoScenarioLoader::class)->load())->toThrow(RuntimeException::class);
    expect($existing->fresh()->role)->toBe('admin')->and(DB::table('demo_runs')->count())->toBe(0)->and(Product::count())->toBe(0);
});

it('transfers a prepared replay without changing identities embedded in evidence or snapshots', function () {
    $report = app(DemoPostgresLoader::class)->load();
    expect($report['counts']['cases'])->toBe(34)->and(DB::getDefaultConnection())->toBe('sqlite');
    foreach (DB::table('evaluations')->get() as $evaluation) {
        $snapshot = json_decode($evaluation->snapshot, true, 512, JSON_THROW_ON_ERROR);
        expect($snapshot['case_pk'])->toBe($evaluation->commercial_case_id);
        foreach ($snapshot['events'] as $event) {
            expect(DB::table('case_events')->where('id', $event['sequence'])->where('event_id', $event['event_id'])->exists())->toBeTrue();
        }
    }
    $rerun = app(DemoPostgresLoader::class)->load();
    expect($rerun['counts'])->toBe($report['counts']);
    Http::assertNothingSent();
    Mail::assertNothingSent();
});

it('rolls back a failed replay and its private files before retrying the same run', function () {
    expect(fn () => app(DemoScenarioLoader::class)->load(function () {
        throw new RuntimeException('Injected failure after stored attachments');
    }))->toThrow(RuntimeException::class, 'Injected failure');
    $run = DB::table('demo_runs')->first();
    expect($run->status)->toBe('failed')->and(User::count())->toBe(4)->and(Product::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
    $report = app(DemoScenarioLoader::class)->load();
    expect($report['run_id'])->toBe($run->id)->and($report['counts']['cases'])->toBe(34);
});

it('continues seeded drafts and proposals through the real HTTP workflow with private access', function () {
    $report = app(DemoScenarioLoader::class)->load();
    foreach (['A02', 'A05', 'A06', 'A08'] as $key) {
        $id = DB::table('demo_records')->where('demo_run_id', $report['run_id'])->where('logical_key', $key)->value('entity_id');
        $case = CommercialCase::findOrFail($id);
        $seller = User::where('email', 'jorge@gmail.com')->firstOrFail();
        $buyer = User::findOrFail(Customer::findOrFail($case->customer_id)->account_id);
        $oldCount = QuoteVersion::where('commercial_case_id', $id)->count();
        $this->actingAs($buyer)->get('/my/quotes/'.$case->public_id)->assertInertia(fn (AssertableInertia $page) => $page->missing('draft')->has('quotes', $oldCount));
        $this->actingAs($seller)->post('/cases/'.$id.'/quote', ['customer_id' => $case->customer_id, 'expected_revision' => $case->revision,
            'draft_revision' => DB::table('quote_drafts')->where('commercial_case_id', $id)->value('revision'),
            'title' => 'Continuación '.$key, 'valid_until' => now()->addWeek()->format('Y-m-d'), 'authorization_reference' => 'Autorización de la prueba de continuidad',
            'tax' => '384.00', 'shipping' => '150.00', 'discount' => '0.00', 'terms' => 'Nueva versión acordada',
            'lines' => [['product_id' => Product::where('sku', 'DEMO-REG-NEG-M')->firstOrFail()->id, 'quantity' => 24, 'unit_price' => '100.00', 'supply_mode' => 'manufacture', 'stock_units' => 0]]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $quote = QuoteVersion::where('commercial_case_id', $id)->latest('version')->firstOrFail();
        expect($quote->version)->toBe($oldCount + 1)->and($quote->total_cents)->toBe(293400);
        expect(DB::table('quote_drafts')->where('commercial_case_id', $id)->exists())->toBeFalse();
        $this->actingAs($buyer)->post('/journey/'.$id.'/decision', ['event_id' => (string) Str::uuid(), 'kind' => 'accept', 'quote_version' => $quote->version,
            'lines' => [['line_id' => 'L1', 'quantity' => 24]]])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($seller)->post('/journey/'.$id.'/result', ['event_id' => (string) Str::uuid(), 'kind' => 'formalized', 'amount' => '2934.00',
            'reference' => 'Formalización de prueba', 'occurred_at' => now()->toIso8601String(), 'close' => false])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/journey/'.$id.'/result', ['event_id' => (string) Str::uuid(), 'kind' => 'payment', 'amount' => '2934.00',
            'reference' => 'Pago de prueba aislada', 'occurred_at' => now()->toIso8601String(), 'close' => true])->assertRedirect()->assertSessionHasNoErrors();
        expect($case->fresh()->status)->toBe('accepted');
    }
    $other = User::where('email', 'nicolas@gmail.com')->firstOrFail();
    $this->actingAs($other)->get('/my/quotes/'.$case->public_id)->assertNotFound();
    $attachment = DB::table('chat_messages')->whereNotNull('attachment_path')->first();
    $this->get('/attachments/'.$attachment->id)->assertNotFound();
    $owner = User::findOrFail($attachment->author_id);
    $this->actingAs($owner)->get('/attachments/'.$attachment->id)->assertOk();
    app(DemoIntegrityAudit::class)->verify($report['run_id']);
    Http::assertNothingSent();
    Mail::assertNothingSent();
});

it('separates demo metrics and preserves historical profile evidence at its cutoff', function () {
    app(DemoScenarioLoader::class)->load();
    $seller = User::where('role', 'commerce')->firstOrFail();
    $this->actingAs($seller)->get('/customers?origin=demo')->assertInertia(fn (AssertableInertia $page) => $page->has('customers.data', 10));
    $this->get('/customers?origin=real')->assertInertia(fn (AssertableInertia $page) => $page->has('customers.data', 0));
    $this->get('/requests')->assertInertia(fn (AssertableInertia $page) => $page->has('cases', 0));
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get('/admin?origin=demo')->assertInertia(fn (AssertableInertia $page) => $page->where('summary.customers', 10)->where('summary.quotes', 46));
    $this->get('/admin?origin=real')->assertInertia(fn (AssertableInertia $page) => $page->where('summary.customers', 0)->where('summary.quotes', 0));
    $camila = Customer::where('email', 'camila@gmail.com')->firstOrFail();
    $facts = DB::table('profile_facts')->where('customer_id', $camila->id)->where('name', 'fit_preferido')->orderBy('id')->get();
    expect($facts->pluck('status')->all())->toBe(['superseded', 'active', 'conflicting']);
    $before = app(CustomerProfile::class)->context($camila, CarbonImmutable::parse($facts->first()->created_at));
    expect(collect($before['properties'])->firstWhere('name', 'fit_preferido')['value'])->toBe('Oversize');
    $after = app(CustomerProfile::class)->context($camila, CarbonImmutable::now());
    expect(collect($after['properties'])->firstWhere('name', 'fit_preferido')['value'])->toBe('Regular');
    $real = CommercialCase::create(['data_set_id' => CommercialCase::firstOrFail()->data_set_id, 'external_id' => 'REAL-CONTROL', 'title' => 'Control sin datos demo']);
    $features = app(BusinessHistory::class)->features($real, CarbonImmutable::now());
    expect($features[14])->toBe(0.0)->and($features[15])->toBe(0.0);
});
