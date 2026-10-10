<?php

namespace App\Domain\Demo;

use App\Domain\Cases\CatalogService;
use App\Domain\Cases\ConversationService;
use App\Domain\Cases\CustomerJourney;
use App\Domain\Cases\CustomerProfile;
use App\Domain\Cases\DraftService;
use App\Domain\Cases\QuoteService;
use App\Domain\Cases\RecommendationReviewService;
use App\Domain\Evaluation\EvaluationService;
use App\Models\CommercialCase;
use App\Models\Customer;
use App\Models\Product;
use App\Models\QuoteVersion;
use App\Models\Recommendation;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final class DemoScenarioLoader
{
    public const KEY = 'operational-demo-v1';

    /** @var array<string, User> */
    private array $accounts = [];

    /** @var array<string, User> */
    private array $sellers = [];

    /** @var array<string, array<string, mixed>> */
    private array $people = [];

    /** @var array<string, array<string, mixed>> */
    private array $scenarios = [];

    /** @var array<string, array<string, mixed>> */
    private array $catalog = [];

    /** @var array<string, int> */
    private array $caseIds = [];

    /** @var array<string, Product> */
    private array $products = [];

    private string $runId;

    private CarbonImmutable $reference;

    public function fixturePath(string $name): string
    {
        return database_path('seeders/fixtures/'.self::KEY.'/'.$name);
    }

    /** @return array<mixed> */
    public function fixture(string $name): array
    {
        return json_decode((string) file_get_contents($this->fixturePath($name)), true, 512, JSON_THROW_ON_ERROR);
    }

    public function hash(): string
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->fixturePath(''), \FilesystemIterator::SKIP_DOTS));
        $hashes = [];
        foreach ($files as $file) {
            if ($file->isFile()) {
                $hashes[str_replace('\\', '/', substr($file->getPathname(), strlen($this->fixturePath(''))))] = hash_file('sha256', $file->getPathname());
            }
        }
        ksort($hashes);

        return hash('sha256', json_encode($hashes, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    public function preflight(): array
    {
        DemoPolicy::requireLoadingEnabled();
        $run = DB::table('demo_runs')->where('key', self::KEY)->first();
        if ($run && $run->manifest_hash !== $this->hash()) {
            throw new RuntimeException('El manifiesto difiere de la ejecución existente. No se sobrescribirá.');
        }
        if ($run && $run->status === 'loading') {
            throw new RuntimeException('Existe una carga en curso o interrumpida; requiere revisión antes de reintentar.');
        }
        if ($run && $run->status === 'ready') {
            return ['installed' => true, 'run_id' => $run->id];
        }
        Validator::make(['password' => config('demo.customer_password')], ['password' => 'required|string|min:8'])->validate();
        foreach (['jorge', 'dani', 'jesus', 'sau'] as $name) {
            if (! User::where('email', $name.'@gmail.com')->where('role', 'commerce')->exists()) {
                throw new RuntimeException('Falta la cuenta de comercio requerida: '.$name);
            }
        }
        $people = $this->fixture('customers.json');
        if (User::whereIn('email', array_column($people, 'email'))->exists()) {
            throw new RuntimeException('Un correo del escenario ya existe fuera de esta ejecución. No se modifica.');
        }
        if (Product::where('sku', 'like', 'DEMO-%')->exists()) {
            throw new RuntimeException('Hay SKUs DEMO existentes fuera de una ejecución completa.');
        }
        $data = $this->fixture('scenarios.json');
        if (count($people) !== 12 || count($data['cases']) !== 34 || count(array_unique(array_column($data['operations'], 'key'))) !== count($data['operations'])) {
            throw new RuntimeException('Manifiesto incoherente.');
        }
        foreach ($data['operations'] as $operation) {
            if ($operation['offset_minutes'] > 0) {
                throw new RuntimeException('Una operación de la carga está en el futuro.');
            }
            if (! empty($operation['attachment']) && ! is_file($this->fixturePath('attachments/'.$operation['attachment']))) {
                throw new RuntimeException('Falta un adjunto del escenario.');
            }
        }

        return ['installed' => false, 'expected' => $this->fixture('manifest.json'), 'manifest_hash' => $this->hash()];
    }

    /** @return array<string, mixed> */
    public function load(?\Closure $progress = null): array
    {
        DemoPolicy::requireLoadingEnabled();
        $lock = Cache::lock('byecode:operational-demo-load', 3600);
        if (! $lock->get()) {
            throw new RuntimeException('Otra carga de demostración está en curso.');
        }
        $previousContext = app(DemoContext::class);
        $context = new DemoContext;
        $previousClock = Carbon::getTestNow();
        $previousImmutableClock = CarbonImmutable::getTestNow();
        try {
            $preflight = $this->preflight();
            if ($preflight['installed']) {
                return app(DemoIntegrityAudit::class)->verify($preflight['run_id']);
            }
            $existing = DB::table('demo_runs')->where('key', self::KEY)->first();
            $this->runId = $existing ? $existing->id : (string) Str::uuid();
            $this->reference = $existing ? CarbonImmutable::parse($existing->reference_at, 'UTC') : CarbonImmutable::now('UTC')->startOfMinute();
            DB::table('demo_runs')->updateOrInsert(['id' => $this->runId], ['key' => self::KEY, 'version' => '1.0.0', 'seed' => 20261010,
                'reference_at' => $this->reference, 'loaded_at' => now(), 'manifest_hash' => $this->hash(), 'status' => 'loading',
                'semantic_policy' => 'rules_only', 'created_at' => $existing->created_at ?? now(), 'updated_at' => now()]);
            $context->loading = true;
            $context->runId = $this->runId;
            app()->instance(DemoContext::class, $context);
            foreach (['jorge', 'dani', 'jesus', 'sau'] as $name) {
                $this->sellers[$name] = User::where('email', $name.'@gmail.com')->where('role', 'commerce')->firstOrFail();
            }
            $data = $this->fixture('scenarios.json');
            foreach ($data['cases'] as $scenario) {
                $this->scenarios[$scenario['key']] = $scenario;
            }
            DB::transaction(function () use ($data, $progress) {
                $this->clock($this->reference->subDays(271));
                $this->createCatalog();
                foreach ($this->fixture('customers.json') as $person) {
                    $user = new User(['name' => $person['name'], 'email' => $person['email'], 'password' => config('demo.customer_password')]);
                    $user->forceFill(['role' => 'customer', 'demo_run_id' => $this->runId, 'email_verified_at' => now(), 'must_change_password' => false])->save();
                    $this->accounts[$person['key']] = $user;
                    $this->people[$person['key']] = $person;
                    $this->remember('users', $user->id, 'account.'.$person['key']);
                }
                foreach ($data['operations'] as $index => $operation) {
                    $this->clock($this->reference->addMinutes($operation['offset_minutes']));
                    $this->perform($operation);
                    if ($progress && ($index + 1) % 25 === 0) {
                        $progress('Operaciones reproducidas: '.($index + 1).' / '.count($data['operations']));
                    }
                }
                $this->clock($this->reference);
                foreach ($this->caseIds as $id) {
                    $this->evaluate(CommercialCase::findOrFail($id), false);
                }
                foreach (Customer::where('demo_run_id', $this->runId)->get() as $customer) {
                    app(CustomerProfile::class)->process($customer);
                    $this->remember('customers', $customer->id, 'customer.'.$customer->account_id);
                }
                $this->registerChildren();
                $report = app(DemoIntegrityAudit::class)->verify($this->runId, true);
                DB::table('demo_runs')->where('id', $this->runId)->update(['status' => 'ready', 'report' => json_encode($report, JSON_THROW_ON_ERROR), 'updated_at' => $this->reference]);
            });

            return app(DemoIntegrityAudit::class)->verify($this->runId);
        } catch (\Throwable $e) {
            if (isset($this->runId) && DB::table('demo_runs')->where('id', $this->runId)->value('status') !== 'ready') {
                Storage::disk('local')->delete($context->files);
                DB::table('demo_runs')->where('id', $this->runId)->update(['status' => 'failed', 'updated_at' => $this->reference]);
            }
            throw $e;
        } finally {
            Carbon::setTestNow($previousClock);
            CarbonImmutable::setTestNow($previousImmutableClock);
            app()->instance(DemoContext::class, $previousContext);
            $lock->release();
        }
    }

    private function clock(CarbonImmutable $time): void
    {
        Carbon::setTestNow($time);
        CarbonImmutable::setTestNow($time);
    }

    private function createCatalog(): void
    {
        foreach ($this->fixture('catalog.json') as $style) {
            $this->catalog[$style['key']] = $style;
            $service = app(CatalogService::class);
            $service->style($this->sellers['jorge'], ['name' => $style['name'], 'category' => $style['category'], 'published' => true, 'details' => $style['details']]);
            $id = (int) DB::table('product_styles')->where('demo_run_id', $this->runId)->where('name', $style['name'])->value('id');
            $this->remember('product_styles', $id, 'style.'.$style['key']);
            foreach ($style['colors'] as $color) {
                foreach ($style['sizes'] as $size) {
                    $sku = 'DEMO-'.$style['key'].'-'.($color === 'Negro' ? 'NEG' : 'NAT').'-'.$size;
                    $service->variant($this->sellers['jorge'], ['style_id' => $id, 'sku' => $sku, 'fit' => $style['fit'], 'color' => $color, 'size' => $size,
                        'composition' => $style['composition'], 'gsm' => $style['gsm'], 'moq' => $style['moq'], 'stock' => $style['stock'],
                        'source' => 'Observación sintética del catálogo de demostración', 'published' => true,
                        'details' => ['color_code' => $color === 'Negro' ? '#171717' : '#eee9dc', 'measurements' => 'Pecho/largo en plano: S 50/68, M 53/71, L 56/74 cm.',
                            'tolerance' => '±1.5 cm; confirmar muestra.', 'availability' => $style['stock'] === null ? 'Por confirmar' : 'Existencia observada; no es reserva.']]);
                    $product = Product::where('sku', $sku)->firstOrFail();
                    $this->products[$sku] = $product;
                    $this->remember('products', $product->id, 'product.'.$sku);
                }
            }
        }
    }

    /** @param array<string, mixed> $operation */
    private function perform(array $operation): void
    {
        $scenario = $this->scenarios[$operation['case']];
        $buyer = $this->accounts[$scenario['customer']];
        $seller = $this->sellers[$scenario['seller']];
        $id = $this->eventId($operation['key']);
        $product = $this->products['DEMO-'.$scenario['style'].'-NEG-M'];
        if ($operation['kind'] === 'request') {
            $case = app(CustomerJourney::class)->submit($buyer, ['submission_id' => $id,
                'company' => $this->people[$scenario['customer']]['company'], 'lines' => [['product_id' => $product->id, 'quantity' => $scenario['quantity']]]]);
            $case->update(['title' => $scenario['title'], 'assigned_to' => $operation['case'] === 'A02' ? null : $seller->id]);
            $this->caseIds[$scenario['key']] = $case->id;
            $this->remember('commercial_cases', $case->id, $scenario['key']);

            return;
        }
        $case = CommercialCase::findOrFail($this->caseIds[$scenario['key']]);
        $conversations = app(ConversationService::class);
        switch ($operation['kind']) {
            case 'quote':
                $input = $this->quoteInput($scenario, $case, $product, $operation['version'], $operation['valid_days']);
                app(QuoteService::class)->save($seller, $input, $case);
                break;
            case 'message':
                $file = empty($operation['attachment']) ? null : new UploadedFile($this->fixturePath('attachments/'.$operation['attachment']), $operation['attachment'], 'image/png', null, true);
                $conversations->message($operation['sender'] === 'buyer' ? $buyer : $seller, $case,
                    ['event_id' => $id, 'body' => $operation['body'], 'quote_version' => QuoteVersion::where('commercial_case_id', $case->id)->max('version')], $file);
                if ($file) {
                    $path = DB::table('chat_messages')->where('event_id', $id)->value('attachment_path');
                    app(DemoContext::class)->files[] = $path;
                }
                break;
            case 'decision':
                $quote = QuoteVersion::where('commercial_case_id', $case->id)->latest('version')->firstOrFail();
                $conversations->decision($buyer, $case, ['event_id' => $id, 'quote_version' => $quote->version, 'kind' => $operation['decision'],
                    'lines' => array_map(fn ($line) => ['line_id' => $line['line_id'], 'quantity' => ! empty($operation['partial']) ? intdiv($line['quantity'], 2) : $line['quantity']], $quote->lines),
                    'conditions' => $operation['conditions'] ?? null]);
                break;
            case 'result':
                $total = (int) QuoteVersion::where('commercial_case_id', $case->id)->latest('version')->value('total_cents');
                $fraction = $operation['fraction'] ?? 0;
                $amount = $fraction === 70 ? $total - intdiv($total * 30, 100) : intdiv($total * $fraction, 100);
                $conversations->result($seller, $case, ['event_id' => $id, 'kind' => $operation['result'],
                    'amount' => isset($operation['fraction']) ? $this->money($amount) : null, 'close' => $operation['close'],
                    'reference' => 'DEMO-'.strtoupper($operation['key']).' · registro sintético del escenario; sin transacción bancaria.', 'occurred_at' => now()->toIso8601String()]);
                break;
            case 'constraint':
                $data = array_key_exists('contact_allowed', $operation) ? ['contact_allowed' => $operation['contact_allowed']] : ['contact_not_before' => $this->reference->addMinutes($operation['contact_not_before_minutes'])->toIso8601ZuluString()];
                app(QuoteService::class)->event($case, 'constraint.updated', $data, $id);
                app(CustomerJourney::class)->record($case, $seller, 'constraint.updated', $data, $id);
                break;
            case 'withdraw':
                $conversations->withdraw($seller, $case, ['version' => 1, 'reason' => $operation['reason']]);
                break;
            case 'draft':
                $data = $this->quoteInput($scenario, $case, $product, 2, 14);
                $data['draft_revision'] = 0;
                $data['lines'] = array_map(fn ($line) => array_map(fn ($value) => (string) $value, $line), $data['lines']);
                $data['lines'][0]['unit_price'] = '';
                $data['authorization_reference'] = null;
                app(DraftService::class)->store($seller, $case, $data);
                break;
            case 'view':
                app(QuoteService::class)->event($case, 'proposal.viewed', ['section' => 'conditions'], $id);
                app(CustomerJourney::class)->record($case, $buyer, 'proposal.version_viewed', ['version' => 1, 'consent' => true], $id);
                DB::table('activity_events')->where('event_id', $id)->update(['source' => 'browser_unverified', 'session_key' => 'demo-consented-'.$buyer->id]);
                break;
            case 'profile':
                foreach ($this->people[$scenario['customer']]['profile'] as $name => $value) {
                    $this->fact($case, $seller, 'commercial.'.$name, 'cliente', $value, 'confirmed', $operation['evidence']);
                }
                if ($scenario['customer'] === 'diego') {
                    $this->fact($case, $seller, 'empaque_preferido', 'cliente', 'Bolsa individual', 'confirmed', $operation['evidence']);
                }
                break;
            case 'fact':
                $this->fact($case, $seller, $operation['name'], $operation['scope'], $operation['value'], $operation['nature'], $operation['evidence']);
                break;
            case 'evaluate':
                $this->evaluate($case, $operation['review'] ?? false);
                break;
            default: throw new RuntimeException('Operación desconocida: '.$operation['kind']);
        }
    }

    private function fact(CommercialCase $case, User $seller, string $name, string $scope, mixed $value, string $nature, string $evidence): void
    {
        $event = DB::table('activity_events')->where('event_id', $this->eventId($evidence))->firstOrFail();
        app(CustomerProfile::class)->fact(Customer::findOrFail($case->customer_id), ['name' => $name, 'scope' => $scope,
            'value_json' => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'confidence' => $nature === 'confirmed' ? 'high' : 'low', 'evidence' => [$event->id]],
            $nature, null, $nature === 'confirmed' ? $seller->id : null, $nature === 'confirmed' ? 'demo_replay' : 'demo_scenario');
    }

    /** @param array<string, mixed> $scenario
     * @return array<string, mixed>
     */
    private function quoteInput(array $scenario, CommercialCase $case, Product $product, int $version, int $days): array
    {
        $quantity = $scenario['quantity'];
        $unit = $this->catalog[$scenario['style']]['unit_price_cents'] - ($version - 1) * 50;
        $subtotal = $unit * $quantity;

        return ['customer_id' => $case->customer_id, 'title' => $scenario['title'], 'expected_revision' => $case->revision,
            'valid_until' => now()->addDays($days)->setTimezone('America/Mexico_City')->format('Y-m-d'),
            'terms' => 'Precios de demostración en MXN. '.($version > 1 ? 'Esta versión incorpora un ajuste de precio autorizado; conserva las cantidades solicitadas. ' : '').'Abastecimiento sujeto a confirmación operativa. Aceptar no registra pagos ni cierre.',
            'authorization_reference' => 'DEMO-AUT-'.$scenario['key'].'-V'.$version,
            'tax' => $this->money(intdiv($subtotal * 16 + 50, 100)), 'shipping' => '150.00', 'discount' => '0.00',
            'lines' => [['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $this->money($unit), 'supply_mode' => $scenario['supply'],
                'stock_units' => match ($scenario['supply']) {
                    'stock' => $quantity, 'mixed' => intdiv($quantity, 2), default => 0
                }]]];
    }

    private function evaluate(CommercialCase $case, bool $review): void
    {
        $service = app(EvaluationService::class);
        $evaluation = $service->request($case->refresh());
        $service->run($evaluation->id);
        $recommendation = Recommendation::where('evaluation_id', $evaluation->id)->first();
        if ($review && $recommendation && $recommendation->publication_status === 'awaiting_review') {
            $seller = User::findOrFail($case->assigned_to);
            $reviews = app(RecommendationReviewService::class);
            $reviews->review($seller, $recommendation, ['decision' => 'approved', 'reason' => 'Revisión de demostración: conservar el bloqueo documentado; aprobar no ejecuta contacto.']);
            $reviews->feedback($seller, $recommendation, ['verdict' => 'useful', 'reason' => 'La restricción corresponde a la evidencia del escenario de prueba.']);
        }
    }

    private function eventId(string $key): string
    {
        return Uuid::uuid5($this->runId, $key)->toString();
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function remember(string $table, int $id, string $key): void
    {
        $row = DB::table($table)->where('id', $id)->firstOrFail();
        DB::table('demo_records')->insertOrIgnore(['demo_run_id' => $this->runId, 'entity_type' => $table, 'entity_id' => (string) $id,
            'logical_key' => $key, 'baseline_hash' => hash('sha256', json_encode($row, JSON_THROW_ON_ERROR))]);
    }

    private function registerChildren(): void
    {
        foreach (['purchase_requests', 'quote_versions', 'quote_drafts', 'chat_messages', 'buyer_decisions', 'sale_records', 'case_events', 'activity_events', 'evaluations', 'recommendations'] as $table) {
            foreach (DB::table($table)->whereIn('commercial_case_id', $this->caseIds)->pluck('id') as $id) {
                $this->remember($table, $id, $table.'.'.$id);
            }
        }
        $customerIds = Customer::where('demo_run_id', $this->runId)->pluck('id');
        foreach (['profile_facts', 'profile_runs'] as $table) {
            foreach (DB::table($table)->whereIn('customer_id', $customerIds)->pluck('id') as $id) {
                $this->remember($table, $id, $table.'.'.$id);
            }
        }
        $recommendations = DB::table('recommendations')->whereIn('commercial_case_id', $this->caseIds)->pluck('id');
        foreach (['human_reviews', 'recommendation_feedback'] as $table) {
            foreach (DB::table($table)->whereIn('recommendation_id', $recommendations)->pluck('id') as $id) {
                $this->remember($table, $id, $table.'.'.$id);
            }
        }
        foreach (DB::table('mail_deliveries')->where('demo_run_id', $this->runId)->pluck('id') as $id) {
            $this->remember('mail_deliveries', $id, 'mail_deliveries.'.$id);
        }
        foreach (DB::table('case_events')->whereIn('commercial_case_id', $this->caseIds)->distinct()->pluck('import_batch_id') as $id) {
            $this->remember('import_batches', $id, 'import_batches.'.$id);
        }
        foreach (DB::table('demo_records')->where('demo_run_id', $this->runId)->whereIn('entity_type', ['commercial_cases', 'customers', 'users'])->get() as $record) {
            $row = DB::table($record->entity_type)->where('id', $record->entity_id)->firstOrFail();
            DB::table('demo_records')->where('id', $record->id)->update(['baseline_hash' => hash('sha256', json_encode($row, JSON_THROW_ON_ERROR))]);
        }
    }
}
