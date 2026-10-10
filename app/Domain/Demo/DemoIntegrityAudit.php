<?php

namespace App\Domain\Demo;

use App\Models\QuoteVersion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class DemoIntegrityAudit
{
    /** @return array<string, mixed> */
    public function verify(string $runId, bool $initial = false): array
    {
        $run = DB::table('demo_runs')->where('id', $runId)->firstOrFail();
        $cases = DB::table('commercial_cases')->where('demo_run_id', $runId)->get();
        $ids = $cases->pluck('id');
        $customers = DB::table('customers')->where('demo_run_id', $runId)->get();
        $messages = DB::table('chat_messages')->whereIn('commercial_case_id', $ids)->get();
        $sales = DB::table('sale_records')->whereIn('commercial_case_id', $ids)->get();
        $quotes = QuoteVersion::whereIn('commercial_case_id', $ids)->get();
        $counts = ['customers' => DB::table('users')->where('demo_run_id', $runId)->count(), 'crm' => $customers->count(),
            'cases' => $cases->count(), 'closed' => $cases->where('status', '!=', 'open')->count(), 'open' => $cases->where('status', 'open')->count(),
            'quotes' => $quotes->count(), 'drafts' => DB::table('quote_drafts')->whereIn('commercial_case_id', $ids)->count(),
            'formalized' => $sales->where('kind', 'formalized')->count(), 'payments' => $sales->where('kind', 'payment')->count(), 'sales' => $sales->count(),
            'styles' => DB::table('product_styles')->where('demo_run_id', $runId)->count(), 'products' => DB::table('products')->where('demo_run_id', $runId)->count(),
            'attachments' => $messages->whereNotNull('attachment_path')->count(), 'messages' => $messages->count(),
            'requests' => DB::table('purchase_requests')->whereIn('commercial_case_id', $ids)->count(),
            'facts' => DB::table('profile_facts')->whereIn('customer_id', $customers->pluck('id'))->count(),
            'activities' => DB::table('activity_events')->whereIn('commercial_case_id', $ids)->count()];
        $errors = [];
        if ($run->manifest_hash !== app(DemoScenarioLoader::class)->hash()) {
            $errors[] = 'El manifiesto no corresponde a la carga instalada.';
        }
        foreach (['users', 'customers', 'commercial_cases', 'quote_versions', 'sale_records', 'chat_messages', 'profile_facts'] as $table) {
            $registered = DB::table('demo_records')->where('demo_run_id', $runId)->where('entity_type', $table)->pluck('entity_id');
            if ($registered->count() !== DB::table($table)->whereIn('id', $registered)->count()) {
                $errors[] = 'Faltan registros del historial inicial en '.$table.'. No se recrean automáticamente.';
            }
        }
        if ($initial) {
            foreach (app(DemoScenarioLoader::class)->fixture('manifest.json') as $key => $value) {
                if (array_key_exists($key, $counts) && $counts[$key] !== $value) {
                    $errors[] = "Conteo $key: {$counts[$key]}, esperado $value.";
                }
            }
            if ($counts['requests'] !== 34 || $cases->where('status', 'accepted')->count() !== 16 || $cases->where('status', 'rejected')->count() !== 4 || $cases->where('status', 'cancelled')->count() !== 2) {
                $errors[] = 'Solicitudes o resultados históricos incoherentes.';
            }
        }
        foreach ($customers as $customer) {
            if (! DB::table('users')->where('id', $customer->account_id)->where('role', 'customer')->where('demo_run_id', $runId)->exists()) {
                $errors[] = 'Cliente sin cuenta de demostración correspondiente: '.$customer->id;
            }
        }
        foreach ($cases as $case) {
            if (! $customers->contains('id', $case->customer_id) || $case->integrity_blocked) {
                $errors[] = 'Caso sin cliente o con historial conflictivo: '.$case->id;
            }
            $versions = $quotes->where('commercial_case_id', $case->id)->sortBy('version')->values();
            foreach ($versions as $i => $quote) {
                $subtotal = 0;
                foreach ($quote->lines as $line) {
                    $subtotal += $line['quantity'] * $line['unit_price_cents'];
                    if ($line['total_cents'] !== $line['quantity'] * $line['unit_price_cents']) {
                        $errors[] = 'Importe de línea incoherente: '.$quote->id;
                    }
                }
                if ($quote->version !== $i + 1 || $quote->subtotal_cents !== $subtotal || $quote->total_cents !== $subtotal + $quote->tax_cents + $quote->shipping_cents - $quote->discount_cents) {
                    $errors[] = 'Versión o total incoherente: '.$quote->id;
                }
            }
            if ($initial && $case->status === 'accepted') {
                $rows = $sales->where('commercial_case_id', $case->id);
                if ($rows->where('kind', 'payment')->sum('amount_cents') !== $rows->where('kind', 'formalized')->sum('amount_cents') || $rows->where('kind', 'formalized')->sum('amount_cents') !== $versions->last()?->total_cents) {
                    $errors[] = 'Pagos, formalización y cotización histórica no concuerdan: '.$case->id;
                }
            }
        }
        foreach ($messages->whereNotNull('attachment_path') as $message) {
            if (! Storage::disk('local')->exists($message->attachment_path)) {
                $errors[] = 'Adjunto privado ausente: '.$message->id;
            }
        }
        $batchIds = DB::table('case_events')->whereIn('commercial_case_id', $ids)->distinct()->pluck('import_batch_id');
        foreach (DB::table('import_batches')->whereIn('id', $batchIds)->get() as $batch) {
            if (! $batch->path || ! Storage::disk('local')->exists($batch->path)
                || hash('sha256', Storage::disk('local')->get($batch->path)) !== $batch->sha256) {
                $errors[] = 'Archivo original del historial ausente o alterado: '.$batch->id;
            }
        }
        foreach (DB::table('profile_facts')->whereIn('customer_id', $customers->pluck('id'))->get() as $fact) {
            foreach (json_decode($fact->evidence, true, 32, JSON_THROW_ON_ERROR) as $id) {
                $event = DB::table('activity_events')->where('id', $id)->where('customer_id', $fact->customer_id)->first();
                if (! $event || CarbonImmutable::parse($event->occurred_at)->greaterThan(CarbonImmutable::parse($fact->observed_at))) {
                    $errors[] = 'Evidencia ajena, ausente o futura: '.$fact->id;
                }
            }
        }
        if (DB::table('evaluations')->whereIn('commercial_case_id', $ids)->where('provider', '!=', 'rules')->exists()
            || DB::table('ai_usages')->whereIn('evaluation_id', DB::table('evaluations')->whereIn('commercial_case_id', $ids)->select('id'))->exists()
            || DB::table('ai_usages')->whereIn('profile_run_id', DB::table('profile_runs')->whereIn('customer_id', $customers->pluck('id'))->select('id'))->exists()) {
            $errors[] = 'Se encontró consumo de IA atribuido a demostración.';
        }
        if (DB::table('mail_deliveries')->where('demo_run_id', $runId)->where('status', '!=', 'suppressed_demo')->exists()) {
            $errors[] = 'Correo de demostración no suprimido.';
        }
        if ($errors) {
            throw new RuntimeException(implode("\n", array_unique($errors)));
        }
        $links = DB::table('demo_records')->where('demo_run_id', $runId)->where('entity_type', 'commercial_cases')->get()->map(function ($record) use ($cases) {
            $case = $cases->firstWhere('id', (int) $record->entity_id);

            return ['scenario' => $record->logical_key, 'case_id' => $case?->id, 'title' => $case?->title,
                'commerce_url' => $case ? '/cases/'.$case->id : null, 'customer_url' => $case ? '/my/quotes/'.$case->public_id : null];
        })->all();

        return ['run_id' => $runId, 'key' => $run->key, 'reference_at' => $run->reference_at, 'verified_at' => now()->toIso8601String(),
            'counts' => $counts, 'integrity' => 'passed', 'semantic_policy' => $run->semantic_policy, 'cases' => $links];
    }
}
