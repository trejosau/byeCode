<?php

use App\Domain\Cases\CustomerJourney;
use App\Domain\Cases\CustomerProfile;
use App\Domain\Cases\Onboarding;
use App\Domain\Cases\QuoteService;
use App\Domain\Prediction\BusinessHistory;
use App\Jobs\DeliverTransactionalMail;
use App\Models\CommercialCase;
use App\Models\Customer;
use App\Models\Product;
use App\Models\QuoteVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\InitialAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);
beforeEach(function () {
    Http::preventStrayRequests();
    Mail::fake();
    Storage::fake('local');
    config(['integrations.openai.api_key' => null, 'onboarding.mail_enabled' => false, 'inertia.ssr.enabled' => false]);
    $this->seller = User::factory()->create(['role' => 'commerce']);
    $this->buyer = User::factory()->create(['role' => 'customer']);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $style = DB::table('product_styles')->insertGetId(['name' => 'Playera', 'category' => 'Playera', 'details' => json_encode(['description' => 'Algodón', 'photos' => []]), 'published' => true, 'created_at' => now(), 'updated_at' => now()]);
    $this->product = Product::create(['user_id' => $this->seller->id, 'style_id' => $style, 'sku' => 'REG-N-M', 'name' => 'Playera', 'category' => 'Playera', 'fit' => 'Regular', 'color' => 'Negro', 'size' => 'M', 'published' => true, 'unit_price_cents' => 987654]);
    $this->input = ['submission_id' => (string) Str::uuid(), 'lines' => [['product_id' => $this->product->id, 'quantity' => 10]], 'message' => 'Necesito revisar la composición'];
});

it('starts one CRM only on first submission and reuses it for future cases', function () {
    $this->actingAs($this->buyer)->get('/my/requests')->assertOk();
    expect(Customer::count())->toBe(0);
    $this->post('/request', $this->input)->assertRedirect()->assertSessionHasNoErrors();
    $first = CommercialCase::firstOrFail();
    expect($first->amount_cents)->toBeNull()->and(Customer::count())->toBe(1);
    $this->post('/request', $this->input)->assertRedirect();
    expect(CommercialCase::count())->toBe(1);
    $this->post('/request', [...$this->input, 'submission_id' => (string) Str::uuid()])->assertRedirect();
    expect(Customer::count())->toBe(1)->and(CommercialCase::count())->toBe(2);
});

it('persists a complete 36 variant selection through authentication and submits it once', function () {
    $lines = [];
    for ($i = 0; $i < 36; $i++) {
        $p = $this->product->replicate();
        $p->sku = 'FULL-'.$i;
        $p->save();
        $lines[] = ['product_id' => $p->id, 'quantity' => 12];
    }
    $this->from('/catalog')->post('/selection/save', ['lines' => $lines])->assertRedirect('/catalog')->assertSessionHas('selection', $lines);
    $this->post('/selection', ['lines' => $lines])->assertRedirect('/request');
    $this->get('/request')->assertRedirect('/login');
    $this->actingAs($this->buyer)->get('/request')->assertInertia(fn (Assert $p) => $p->has('selection', 36));
    $input = [...$this->input, 'lines' => $lines];
    $this->post('/request', $input)->assertSessionHasNoErrors()->assertSessionMissing('selection');
    $this->post('/request', $input)->assertSessionHasNoErrors();
    expect(CommercialCase::count())->toBe(1);
    $case = CommercialCase::firstOrFail();
    app(QuoteService::class)->save($this->seller, ['customer_id' => $case->customer_id, 'expected_revision' => $case->revision,
        'title' => 'Todas las variantes', 'valid_until' => now()->addWeek()->format('Y-m-d'), 'authorization_reference' => 'Condiciones revisadas',
        'tax' => '0', 'shipping' => '0', 'discount' => '0', 'lines' => array_map(fn ($l) => [...$l, 'unit_price' => '100.00'], $lines)], $case);
    expect(QuoteVersion::firstOrFail()->lines)->toHaveCount(36);
    expect(QuoteVersion::firstOrFail()->total_cents)->toBe(4320000);
});

it('rejects invalid or unpublished selection lines without replacing the session', function () {
    $lines = $this->input['lines'];
    $this->withSession(['selection' => $lines])->post('/selection/save', ['lines' => [$lines[0], $lines[0]]])->assertSessionHasErrors();
    $this->post('/selection/save', ['lines' => [['product_id' => $this->product->id, 'quantity' => 1.5]]])->assertSessionHasErrors();
    $this->product->update(['published' => false]);
    $this->post('/selection/save', ['lines' => $lines])->assertSessionHasErrors('lines')->assertSessionHas('selection', $lines);
    $this->actingAs($this->buyer)->post('/request', $this->input)->assertSessionHasErrors('lines');
    expect(CommercialCase::count())->toBe(0);
});
it('never exposes catalog prices and allows unverified accounts to submit', function () {
    $this->get('/catalog')->assertInertia(fn (Assert $p) => $p->component('journey/catalog')->has('variants', 1)->missing('variants.0.unit_price_cents')->missing('variants.0.user_id')->missing('customers'));
    $this->post('/request', $this->input)->assertRedirect('/login');
    $this->buyer->forceFill(['email_verified_at' => null])->save();
    $this->actingAs($this->buyer)->post('/request', $this->input)->assertRedirect()->assertSessionHasNoErrors();
    expect(Customer::count())->toBe(1);
});

it('validates catalog colors and image paths and distinguishes material and weight combinations', function () {
    $variant = ['style_id' => $this->product->style_id, 'sku' => 'BC-TEST-S', 'fit' => 'Clásico', 'color' => 'Natural', 'size' => 'S',
        'composition' => '100% algodón', 'gsm' => 180, 'moq' => 12, 'source' => 'Ficha revisada', 'published' => true,
        'details' => ['color_code' => '#EEE9DC', 'measurements' => null, 'tolerance' => null, 'images' => []]];
    $this->actingAs($this->seller)->post('/products/variants', [...$variant, 'details' => [...$variant['details'], 'color_code' => '#fff']])->assertSessionHasErrors('details.color_code');
    $image = ['view' => 'front', 'path' => '/images/catalog/../../.env', 'alt' => 'Natural', 'width' => 1254, 'height' => 1254];
    $this->post('/products/variants', [...$variant, 'details' => [...$variant['details'], 'images' => [$image]]])->assertSessionHasErrors('details.images.0.path');
    $this->post('/products/variants', $variant)->assertSessionHasNoErrors();
    $this->post('/products/variants', [...$variant, 'sku' => 'DUPLICATE', 'fit' => ' clásico ', 'composition' => '100%  algodón'])->assertStatus(422);
    $this->post('/products/variants', [...$variant, 'sku' => 'BC-TEST-220-S', 'gsm' => 220])->assertSessionHasNoErrors();
    $this->post('/products/variants', [...$variant, 'sku' => 'BC-TEST-MIX-S', 'composition' => '80% algodón / 20% poliéster'])->assertSessionHasNoErrors();
    expect(Product::where('sku', 'like', 'BC-TEST%')->count())->toBe(3);
    $this->get('/catalog')->assertInertia(fn (Assert $page) => $page->missing('variants.0.sku')->missing('variants.0.source')->missing('variants.0.stock'));
});
it('enforces admin read only and commerce sharing with buyer isolation', function () {
    $case = app(CustomerJourney::class)->submit($this->buyer, $this->input);
    $this->actingAs($this->admin)->get('/admin')->assertOk();
    $this->post('/quotes', [])->assertForbidden();
    $this->post('/journey/'.$case->id.'/messages', [])->assertForbidden();
    $this->post('/admin/team', ['name' => 'Nueva comercial', 'email' => 'comercio@example.test'])->assertRedirect();
    expect(User::where('email', 'comercio@example.test')->first()->role)->toBe('commerce');
    $otherSeller = User::factory()->create(['role' => 'commerce']);
    $this->actingAs($otherSeller)->get('/cases/'.$case->id)->assertOk();
    $otherBuyer = User::factory()->create(['role' => 'customer']);
    $this->actingAs($otherBuyer)->get('/my/quotes/'.$case->public_id)->assertNotFound();
    $this->post('/admin/team', [])->assertForbidden();
    $this->get('/customers')->assertForbidden();
});
it('protects physical credentials and preserves existing customer passwords', function () {
    $hash = $this->buyer->password;
    $account = app(Onboarding::class)->create('Different name', $this->buyer->email, 'customer');
    expect($account->password)->toBe($hash)->and(DB::table('mail_deliveries')->count())->toBe(0);
    $account = app(Onboarding::class)->create('Cliente físico', 'physical@example.test', 'customer');
    expect($account->must_change_password)->toBeTrue()->and($account->temporary_password_expires_at->isFuture())->toBeTrue();
    expect(DB::table('mail_deliveries')->first()->encrypted_body)->not->toContain('Contraseña temporal');
    $this->actingAs($account)->get('/my/requests')->assertRedirect('/initial-password');
    $this->post('/initial-password', ['password' => 'DifferentPassword123!', 'password_confirmation' => 'DifferentPassword123!'])->assertRedirect('/dashboard');
    expect($account->fresh()->must_change_password)->toBeFalse();
});
it('seeds an admin idempotently without resetting or escalating an existing account', function () {
    config(['onboarding.admin_email' => 'initial@example.test', 'onboarding.admin_password' => 'InitialPassword123!']);
    $this->seed(InitialAdminSeeder::class);
    $hash = User::where('email', 'initial@example.test')->first()->password;
    $this->seed(InitialAdminSeeder::class);
    expect(User::where('email', 'initial@example.test')->count())->toBe(1)->and(User::where('email', 'initial@example.test')->first()->password)->toBe($hash);
    config(['onboarding.admin_email' => $this->buyer->email]);
    expect(fn () => $this->seed(InitialAdminSeeder::class))->toThrow(RuntimeException::class);
});
it('preserves messages and partial decisions and rejects obsolete versions', function () {
    $case = app(CustomerJourney::class)->submit($this->buyer, $this->input);
    $quote = ['customer_id' => $case->customer_id, 'title' => 'Oferta', 'valid_until' => now()->addWeek()->format('Y-m-d'), 'authorization_reference' => 'Autorización comercial', 'tax' => '0', 'shipping' => '0', 'discount' => '0', 'expected_revision' => $case->revision,
        'lines' => [['product_id' => $this->product->id, 'quantity' => 10, 'unit_price' => '100.50']]];
    $case = app(QuoteService::class)->save($this->seller, $quote, $case);
    $this->actingAs($this->buyer)->get('/my/quotes/'.$case->public_id)->assertInertia(fn (Assert $p) => $p->component('journey/quote')->missing('quotes.0.authorization_reference')->missing('recommendations'));
    $message = ['event_id' => (string) Str::uuid(), 'body' => 'Aceptaría cinco por ahora'];
    $this->post('/journey/'.$case->id.'/messages', $message)->assertRedirect()->assertSessionHasNoErrors();
    $this->post('/journey/'.$case->id.'/messages', $message)->assertRedirect();
    expect(DB::table('chat_messages')->count())->toBe(1);
    $decision = ['event_id' => (string) Str::uuid(), 'kind' => 'accept', 'quote_version' => 1, 'lines' => [['line_id' => 'L1', 'quantity' => 5]]];
    $this->post('/journey/'.$case->id.'/decision', $decision)->assertRedirect()->assertSessionHasNoErrors();
    expect(DB::table('buyer_decisions')->count())->toBe(1)->and($case->fresh()->status)->toBe('open');
    app(QuoteService::class)->save($this->seller, [...$quote, 'expected_revision' => $case->fresh()->revision], $case->fresh());
    $this->post('/journey/'.$case->id.'/decision', [...$decision, 'event_id' => (string) Str::uuid()])->assertStatus(422);
});
it('validates telemetry and never allows it to fabricate commercial results', function () {
    $case = app(CustomerJourney::class)->submit($this->buyer, $this->input);
    $event = ['event_id' => (string) Str::uuid(), 'type' => 'ui.section_visible', 'case_id' => $case->id, 'data' => ['section' => 'products']];
    $this->actingAs($this->buyer)->postJson('/activity', ['events' => [$event]])->assertNoContent();
    $this->postJson('/activity', ['events' => [$event]])->assertNoContent();
    expect(DB::table('activity_events')->where('event_id', $event['event_id'])->count())->toBe(1);
    $this->postJson('/activity', ['events' => [[...$event, 'type' => 'sale.payment']]])->assertUnprocessable();
    $this->postJson('/activity', ['events' => [[...$event, 'data' => ['password' => 'excluded']]]])->assertUnprocessable();
});
it('supports different properties and preserves confirmed facts against conflicting inference', function () {
    $case = app(CustomerJourney::class)->submit($this->buyer, $this->input);
    $customer = Customer::findOrFail($case->customer_id);
    $profile = app(CustomerProfile::class);
    $fact = ['name' => 'Empaque', 'scope' => 'cliente', 'value_json' => '"Sin plástico"', 'confidence' => 'high', 'evidence' => [DB::table('activity_events')->first()->id]];
    $profile->fact($customer, $fact, 'confirmed');
    $profile->fact($customer, [...$fact, 'value_json' => '"Individual"'], 'inferred', 'fake');
    expect(DB::table('profile_facts')->where('status', 'active')->count())->toBe(1)->and(DB::table('profile_facts')->where('status', 'conflicting')->count())->toBe(1);
    expect(fn () => $profile->fact($customer, [...$fact, 'evidence' => [999999]], 'inferred'))->toThrow(RuntimeException::class);
    Http::assertNothingSent();
});

it('returns customers to their selection after login and refuses changed duplicate submissions', function () {
    $this->withSession(['selection' => $this->input['lines']])->actingAs($this->buyer)->get('/dashboard')->assertRedirect('/request');
    $this->post('/request', $this->input)->assertRedirect();
    $this->post('/request', [...$this->input, 'message' => 'Un contenido distinto'])->assertConflict();
    expect(Customer::count())->toBe(1)->and(CommercialCase::count())->toBe(1);
});

it('queues recovery encrypted and discards expired credentials without sending', function () {
    $this->buyer->sendPasswordResetNotification('test-reset-token');
    expect(DB::table('mail_deliveries')->count())->toBe(1);
    $rows = DB::table('mail_deliveries')->get();
    foreach ($rows as $row) {
        expect($row->encrypted_body)->not->toContain('test-reset-token')->not->toContain('http');
        expect($row->expires_at)->not->toBeNull();
    }
    $this->travel(2)->hours();
    config(['onboarding.mail_enabled' => true, 'mail.default' => 'smtp']);
    (new DeliverTransactionalMail($rows[0]->id))->handle();
    expect(DB::table('mail_deliveries')->where('id', $rows[0]->id)->value('status'))->toBe('expired');
    expect(DB::table('mail_deliveries')->where('id', $rows[0]->id)->value('encrypted_body'))->toBeNull();
    Mail::assertNothingSent();
});

it('keeps drafts private and rejects concurrent edits or publication from an old draft', function () {
    $case = app(CustomerJourney::class)->submit($this->buyer, $this->input);
    $draft = ['draft_revision' => 0, 'customer_id' => (string) $case->customer_id, 'expected_revision' => $case->revision,
        'title' => 'Borrador', 'tax' => '0', 'shipping' => '0', 'discount' => '0', 'terms' => 'En revisión',
        'valid_until' => now()->addWeek()->format('Y-m-d'), 'authorization_reference' => 'Referencia verificada',
        'lines' => [['product_id' => (string) $this->product->id, 'quantity' => '10', 'unit_price' => '105']]];
    $this->actingAs($this->seller)->post('/cases/'.$case->id.'/draft', $draft)->assertRedirect()->assertSessionHasNoErrors();
    $this->post('/cases/'.$case->id.'/draft', $draft)->assertConflict();
    $this->post('/cases/'.$case->id.'/quote', $draft)->assertSessionHasErrors('quote');
    $this->actingAs($this->buyer)->get('/my/quotes/'.$case->public_id)->assertInertia(fn (Assert $p) => $p->has('quotes', 0)->missing('draft'));
    $this->actingAs($this->seller)->post('/cases/'.$case->id.'/quote', [...$draft, 'draft_revision' => 1])->assertRedirect()->assertSessionHasNoErrors();
    expect(QuoteVersion::count())->toBe(1)->and(DB::table('quote_drafts')->count())->toBe(0);
    $this->post('/journey/'.$case->id.'/withdraw', ['version' => 1, 'reason' => 'Cambio de disponibilidad'])->assertRedirect();
    $this->actingAs($this->buyer)->post('/journey/'.$case->id.'/decision', ['event_id' => (string) Str::uuid(), 'kind' => 'accept', 'quote_version' => 1, 'lines' => [['line_id' => 'L1', 'quantity' => 10]]])->assertStatus(422);
});

it('keeps uncertain AI batches for explicit bounded retry without losing evidence', function () {
    $case = app(CustomerJourney::class)->submit($this->buyer, $this->input);
    $customer = Customer::findOrFail($case->customer_id);
    $profile = app(CustomerProfile::class);
    config(['integrations.openai.api_key' => 'fake-test-key']);
    Http::fake(['api.openai.com/*' => Http::sequence()->push(['error' => ['message' => 'Synthetic failure']], 503)
        ->push(['status' => 'completed', 'usage' => ['input_tokens' => 100, 'output_tokens' => 10], 'output' => [['content' => [['type' => 'output_text', 'text' => '{"facts":[]}']]]]])
        ->push(['status' => 'completed', 'usage' => ['input_tokens' => 100, 'output_tokens' => 10], 'output' => [['content' => [['type' => 'output_text', 'text' => '{"facts":[]}']]]]])]);
    $profile->process($customer);
    $first = DB::table('profile_runs')->first();
    expect($first->status)->toBe('failed')->and(DB::table('ai_usages')->first()->status)->toBe('uncertain');
    $profile->process($customer);
    expect(DB::table('ai_usages')->count())->toBe(1);
    app(CustomerJourney::class)->record($case, $this->buyer, 'message.received', ['message' => 'Un evento posterior']);
    $profile->process($customer);
    expect(DB::table('profile_runs')->count())->toBe(1);
    $profile->process($customer, true);
    $retry = DB::table('profile_runs')->latest('id')->first();
    expect($retry->status)->toBe('complete')->and($retry->attempt)->toBe(2)->and($retry->cutoff)->toBe($first->cutoff)->and($retry->context)->toBe($first->context);
    $profile->process($customer);
    expect(DB::table('profile_runs')->latest('id')->first()->cutoff)->toBeGreaterThan($first->cutoff);
});

it('rejects invented AI evidence and keeps shared budget consumption', function () {
    $case = app(CustomerJourney::class)->submit($this->buyer, $this->input);
    config(['integrations.openai.api_key' => 'fake-test-key']);
    $result = ['facts' => [['name' => 'Color preferido', 'scope' => 'cliente', 'value_json' => '"Negro"', 'confidence' => 'high', 'evidence' => [999999]]]];
    Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'usage' => ['input_tokens' => 100, 'output_tokens' => 60],
        'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($result)]]]]])]);
    app(CustomerProfile::class)->process(Customer::findOrFail($case->customer_id));
    expect(DB::table('profile_facts')->count())->toBe(0)->and(DB::table('profile_runs')->first()->error)->toBe('invalid_evidence');
    expect(DB::table('ai_usages')->first()->status)->toBe('confirmed');
});

it('uses only facts and outcomes known at the historical cutoff for prediction', function () {
    $case = app(CustomerJourney::class)->submit($this->buyer, $this->input);
    $customer = Customer::findOrFail($case->customer_id);
    $profile = app(CustomerProfile::class);
    $candidate = ['name' => 'Empaque', 'scope' => 'cliente', 'value_json' => '"Reutilizable"', 'confidence' => 'high', 'evidence' => [DB::table('activity_events')->first()->id]];
    $profile->fact($customer, $candidate, 'confirmed');
    $at = CarbonImmutable::now();
    $history = app(BusinessHistory::class);
    $before = $history->features($case, $at);
    $this->travel(1)->hours();
    $profile->fact($customer, [...$candidate, 'value_json' => '"Por caja"'], 'confirmed');
    DB::table('sale_records')->insert(['event_id' => (string) Str::uuid(), 'commercial_case_id' => $case->id, 'author_id' => $this->seller->id,
        'kind' => 'payment', 'amount_cents' => 100000, 'reference' => 'Pago registrado después del corte', 'occurred_at' => $at->subHour(), 'created_at' => now()]);
    expect($history->features($case->fresh(), $at))->toBe($before);
    expect($profile->context($customer, $at)['properties'][0]['value'])->toBe('Reutilizable');
    $run = $history->train($this->seller);
    expect($run->status)->toBe('insufficient_data');
});

it('protects private attachments against other accounts', function () {
    $case = app(CustomerJourney::class)->submit($this->buyer, $this->input);
    $this->actingAs($this->buyer)->post('/journey/'.$case->id.'/messages', ['event_id' => (string) Str::uuid(), 'body' => 'Referencia adjunta',
        'attachment' => UploadedFile::fake()->create('referencia.pdf', 10, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();
    $message = DB::table('chat_messages')->first();
    $this->get('/attachments/'.$message->id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $other = User::factory()->create(['role' => 'customer']);
    $this->actingAs($other)->get('/attachments/'.$message->id)->assertNotFound();
});
