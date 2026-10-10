<?php

namespace App\Domain\Cases;

use App\Domain\Evaluation\DecisionUpdates;
use App\Domain\Events\EventProfile;
use App\Domain\Events\ImportService;
use App\Domain\Support\Utc;
use App\Models\CommercialCase;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerJourney
{
    /** @param array<string, mixed> $data */
    public function record(CommercialCase $case, ?User $actor, string $type, array $data, ?string $id = null): void
    {
        if (! $case->customer_id) {
            return;
        }
        DB::transaction(function () use ($case, $actor, $type, $data, $id) {
            $inserted = DB::table('activity_events')->insertOrIgnore(['event_id' => $id ?? (string) Str::uuid(), 'account_id' => Customer::find($case->customer_id)?->account_id,
                'customer_id' => $case->customer_id, 'commercial_case_id' => $case->id, 'type' => $type, 'actor' => $actor ? $actor->role : 'system',
                'source' => 'application', 'data' => json_encode($data, JSON_THROW_ON_ERROR), 'occurred_at' => now(), 'ingested_at' => now()]);
            if ($inserted) {
                app(DecisionUpdates::class)->caseChanged($case);
                app(DecisionUpdates::class)->customerChanged($case->customer_id);
                // These facts change the customer-wide indicators included in other open cases.
                if ($type === 'request.submitted' || str_starts_with($type, 'sale.')) {
                    CommercialCase::where('customer_id', $case->customer_id)->where('id', '!=', $case->id)->where('status', 'open')->each(function ($related) {
                        app(DecisionUpdates::class)->caseChanged($related);
                    });
                }
            }
        });
    }

    /** @param array<string, mixed> $input */
    public function submit(User $buyer, array $input, ?User $seller = null): CommercialCase
    {
        $input['lines'] = app(CatalogSelection::class)->validate($input['lines'], false);

        return DB::transaction(function () use ($buyer, $input, $seller) {
            $buyer = User::whereKey($buyer->id)->lockForUpdate()->firstOrFail();
            abort_unless($buyer->role === 'customer', 403);
            $existing = DB::table('purchase_requests')->where('submission_id', $input['submission_id'])->first();
            if ($existing) {
                abort_unless($existing->account_id === $buyer->id, 409);
                $original = array_map(fn ($line) => ['product_id' => (int) $line['product_id'], 'quantity' => (int) $line['quantity']], json_decode($existing->lines, true));
                $incoming = array_map(fn ($line) => ['product_id' => (int) $line['product_id'], 'quantity' => (int) $line['quantity']], $input['lines']);
                abort_unless($original === $incoming && $existing->message === ($input['message'] ?? null) && $existing->desired_date === ($input['desired_date'] ?? null), 409, 'La solicitud ya fue enviada con otros datos.');

                return CommercialCase::whereKey($existing->commercial_case_id)->firstOrFail();
            }
            $owner = $seller ?? User::where('role', 'commerce')->orderBy('id')->first();
            abort_unless($owner !== null, 422, 'El equipo comercial todavía no está habilitado.');
            $lines = [];
            foreach ($input['lines'] as $line) {
                $product = Product::where('published', true)->whereHasStylePublished()->whereKey($line['product_id'])->firstOrFail();
                $lines[] = ['product_id' => $product->id, 'sku' => $product->sku, 'name' => $product->name,
                    'fit' => $product->fit, 'gsm' => $product->gsm, 'composition' => $product->composition,
                    'color' => $product->color, 'size' => $product->size, 'quantity' => (int) $line['quantity'],
                    'color_code' => $product->details['color_code'] ?? null, 'images' => $product->details['images'] ?? [],
                    'below_moq' => $product->moq !== null && $line['quantity'] < $product->moq];
            }
            $customer = Customer::firstOrCreate(['account_id' => $buyer->id], ['user_id' => $owner->id,
                'company' => $input['company'] ?? $buyer->name, 'contact_name' => $buyer->name, 'email' => $buyer->email, 'demo_run_id' => $buyer->demo_run_id]);
            $set = app(Workspace::class)->live($owner);
            $external = 'SOL-'.Str::upper(Str::random(10));
            $batch = app(ImportService::class)->import($set, json_encode(['event_id' => (string) Str::uuid(), 'case_id' => $external,
                'event_type' => 'case.opened', 'occurred_at' => Utc::iso(CarbonImmutable::now('UTC')), 'data' => ['status' => 'open', 'proposal_amount_mxn' => null]], JSON_THROW_ON_ERROR), 'Solicitud del cliente', EventProfile::BUSINESS, $buyer->demo_run_id);
            abort_if($batch->errors !== [], 422);
            $case = CommercialCase::where('data_set_id', $set->id)->where('external_id', $external)->firstOrFail();
            $case->update(['demo_run_id' => $buyer->demo_run_id, 'customer_id' => $customer->id, 'public_id' => (string) Str::uuid(), 'title' => 'Solicitud de '.$buyer->name, 'stage' => 'request_received', 'assigned_to' => $seller?->id]);
            DB::table('purchase_requests')->insert(['account_id' => $buyer->id, 'commercial_case_id' => $case->id,
                'submission_id' => $input['submission_id'], 'lines' => json_encode($lines, JSON_THROW_ON_ERROR), 'message' => $input['message'] ?? null,
                'desired_date' => $input['desired_date'] ?? null, 'source' => $seller ? 'physical' : 'self_service', 'created_at' => now(), 'updated_at' => now()]);
            $this->record($case, $seller ?? $buyer, 'request.submitted', ['lines' => $lines, 'desired_date' => $input['desired_date'] ?? null]);
            if (! empty($input['message'])) {
                app(QuoteService::class)->event($case, 'message.received', ['message' => $input['message']]);
                $this->record($case, $seller ?? $buyer, 'message.received', ['message' => $input['message'], 'origin' => $seller ? 'physical_capture' : 'self_service']);
            }

            return $case->refresh();
        }, 3);
    }

    public function authorize(User $user, CommercialCase $case): void
    {
        if ($user->role === 'customer') {
            abort_unless(Customer::whereKey($case->customer_id)->where('account_id', $user->id)->exists(), 404);
        } else {
            app(Workspace::class)->ownCase($user, $case);
        }
    }
}
