<?php

namespace App\Domain\Cases;

use App\Domain\Events\EventProfile;
use App\Domain\Events\ImportService;
use App\Domain\Support\Money;
use App\Domain\Support\Utc;
use App\Models\CaseEvent;
use App\Models\CommercialCase;
use App\Models\Customer;
use App\Models\DataSet;
use App\Models\Product;
use App\Models\QuoteVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class QuoteService
{
    public function __construct(private Workspace $workspace, private ImportService $importer) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function save(User $user, array $input, ?CommercialCase $case = null): CommercialCase
    {
        abort_unless($user->role === 'commerce', 403);
        $set = $case ? $this->workspace->ownCase($user, $case) : $this->workspace->live($user);
        if ($set->mode !== 'live') {
            throw ValidationException::withMessages(['quote' => 'El kit oficial no se edita como una cotización real.']);
        }

        return DB::transaction(function () use ($user, $input, $case, $set) {
            DataSet::whereKey($set->id)->lockForUpdate()->firstOrFail();
            if ($case) {
                $case = CommercialCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
                if ($case->status !== 'open' || $case->revision !== (int) $input['expected_revision']) {
                    throw ValidationException::withMessages(['quote' => 'El caso cambió o está cerrado. Recargue antes de publicar una versión.']);
                }
                $draft = DB::table('quote_drafts')->where('commercial_case_id', $case->id)->first();
                if ($draft && (int) ($input['draft_revision'] ?? -1) !== (int) $draft->revision) {
                    throw ValidationException::withMessages(['quote' => 'Otro comercial modificó el borrador. Recarga antes de publicar.']);
                }
            }
            $customer = Customer::whereKey($input['customer_id'])->firstOrFail();
            if ($case) {
                abort_unless($case->customer_id === $customer->id, 422);
            }
            $lines = [];
            $subtotal = 0;
            foreach ($input['lines'] as $i => $line) {
                $product = Product::whereKey($line['product_id'])->firstOrFail();
                $unit = Money::cents($line['unit_price']);
                $quantity = (int) $line['quantity'];
                $stockUnits = isset($line['stock_units']) ? (int) $line['stock_units'] : null;
                if (($line['supply_mode'] === 'stock' && $stockUnits !== $quantity)
                    || ($line['supply_mode'] === 'manufacture' && $stockUnits !== 0)
                    || ($line['supply_mode'] === 'mixed' && ($stockUnits === null || $stockUnits <= 0 || $stockUnits >= $quantity))) {
                    throw ValidationException::withMessages(['lines.'.$i.'.stock_units' => 'Indica un reparto coherente: stock completo, fabricación sin stock o mezcla con ambas cantidades.']);
                }
                if ($stockUnits !== null && ($stockUnits > $quantity || ($product->stock !== null && $stockUnits > $product->stock))) {
                    throw ValidationException::withMessages(['lines.'.$i.'.stock_units' => 'La cantidad de inventario excede la línea o la existencia observada.']);
                }
                $total = $unit * $quantity;
                $subtotal += $total;
                if ($subtotal > 999999999999) {
                    throw ValidationException::withMessages(['lines' => 'El importe supera el máximo operativo de esta versión.']);
                }
                $lines[] = ['line_id' => 'L'.($i + 1), 'product_id' => $product->id, 'sku' => $product->sku, 'name' => $product->name,
                    'category' => $product->category, 'fit' => $product->fit, 'gsm' => $product->gsm, 'composition' => $product->composition,
                    'color' => $product->color, 'size' => $product->size, 'quantity' => $quantity, 'unit_price_cents' => $unit, 'total_cents' => $total,
                    'supply_mode' => $line['supply_mode'], 'stock_units' => $stockUnits,
                    'manufacture_units' => $stockUnits === null ? null : $quantity - $stockUnits,
                    'observed_stock' => $product->stock, 'stock_verified_at' => $product->verified_at ? Utc::iso($product->verified_at) : null,
                    'moq' => $product->moq, 'below_moq' => $product->moq !== null && $quantity < $product->moq,
                    'fulfillment_confirmed' => false];
            }
            $tax = Money::cents($input['tax']);
            $shipping = Money::cents($input['shipping']);
            $discount = Money::cents($input['discount']);
            if ($discount > $subtotal + $tax + $shipping) {
                throw ValidationException::withMessages(['discount' => 'El descuento supera el total.']);
            }
            $total = $subtotal + $tax + $shipping - $discount;
            if ($total > 999999999999) {
                throw ValidationException::withMessages(['quote' => 'El total supera el máximo operativo de esta versión.']);
            }
            $date = CarbonImmutable::parse($input['valid_until'], 'America/Mexico_City')->endOfDay()->utc();
            if ($date->lessThanOrEqualTo(CarbonImmutable::now())) {
                throw ValidationException::withMessages(['valid_until' => 'La vigencia debe ser futura.']);
            }
            if (! $case) {
                $external = 'COT-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
                $batch = $this->importer->import($set, json_encode(['event_id' => (string) Str::uuid(), 'case_id' => $external, 'event_type' => 'case.opened',
                    'occurred_at' => Utc::iso(CarbonImmutable::now('UTC')), 'data' => ['status' => 'open', 'proposal_amount_mxn' => $total / 100]], JSON_THROW_ON_ERROR), 'Nueva cotización', EventProfile::BUSINESS);
                if ($batch->errors) {
                    throw ValidationException::withMessages(['quote' => 'No se pudo abrir la cotización.']);
                }
                $case = CommercialCase::where('data_set_id', $set->id)->where('external_id', $external)->firstOrFail();
            }
            $case->update(['customer_id' => $customer->id, 'title' => $input['title'], 'public_id' => $case->public_id ?: (string) Str::uuid()]);
            $version = (int) QuoteVersion::where('commercial_case_id', $case->id)->max('version') + 1;
            QuoteVersion::create(['commercial_case_id' => $case->id, 'version' => $version, 'lines' => $lines, 'subtotal_cents' => $subtotal,
                'tax_cents' => $tax, 'shipping_cents' => $shipping, 'discount_cents' => $discount, 'total_cents' => $total,
                'terms' => $input['terms'] ?? null, 'authorization_reference' => $input['authorization_reference'], 'valid_until' => $date, 'created_at' => now()]);
            $this->event($case, 'quote.published', ['version' => $version, 'total_cents' => $total]);
            app(CustomerJourney::class)->record($case, $user, 'quote.published', ['version' => $version, 'total_cents' => $total, 'lines' => $lines, 'terms' => $input['terms'] ?? null]);
            if ($customer->account_id) {
                $recipient = User::findOrFail($customer->account_id);
                app(TransactionalMail::class)->queue('quote:'.$case->id.':'.$version, $recipient->email, 'Tu cotización está lista', 'Consulta tu cotización y conversa con comercio: '.url('/my/quotes/'.$case->public_id));
            }
            DB::table('quote_drafts')->where('commercial_case_id', $case->id)->delete();

            return $case->fresh();
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function event(CommercialCase $case, string $type, array $data, ?string $eventId = null): void
    {
        DB::transaction(function () use ($case, $type, $data, $eventId) {
            DataSet::whereKey($case->data_set_id)->lockForUpdate()->firstOrFail();
            $this->appendEvent($case, $type, $data, $eventId);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function appendEvent(CommercialCase $case, string $type, array $data, ?string $eventId): void
    {
        $set = DataSet::findOrFail($case->data_set_id);
        if ($eventId) {
            $existing = CaseEvent::where('data_set_id', $set->id)->where('event_id', $eventId)->first();
            if ($existing) {
                if ($existing->commercial_case_id !== $case->id || $existing->event_type !== $type || EventProfile::hash($existing->original['data']) !== EventProfile::hash($data)) {
                    throw ValidationException::withMessages(['event' => 'La identidad del evento ya corresponde a otro contenido.']);
                }

                return;
            }
        }
        $at = $set->mode === 'kit' ? $set->evaluation_at : CarbonImmutable::now('UTC');
        $batch = $this->importer->import($set, json_encode(['event_id' => $eventId ?? (string) Str::uuid(), 'case_id' => $case->external_id,
            'event_type' => $type, 'occurred_at' => Utc::iso($at ?? CarbonImmutable::now('UTC')), 'data' => $data], JSON_THROW_ON_ERROR), 'Registro comercial', EventProfile::BUSINESS);
        if ($batch->errors) {
            throw ValidationException::withMessages(['event' => 'El evento no pudo incorporarse. Revise el historial de importaciones.']);
        }
    }
}
