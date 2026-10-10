<?php

namespace App\Domain\Cases;

use App\Domain\Decision\RuleEngine;
use App\Domain\Demo\DemoContext;
use App\Domain\Support\Money;
use App\Models\CommercialCase;
use App\Models\QuoteVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ConversationService
{
    /** @param array<string, mixed> $input */
    public function message(User $user, CommercialCase $case, array $input, ?UploadedFile $attachment = null): void
    {
        abort_unless(in_array($user->role, ['customer', 'commerce'], true), 403);
        app(CustomerJourney::class)->authorize($user, $case);
        $data = Validator::make([...$input, 'attachment' => $attachment], ['event_id' => 'required|uuid', 'body' => 'required|string|min:1|max:8000', 'quote_version' => 'nullable|integer', 'attachment' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120'])->validate();
        DB::transaction(function () use ($user, $case, $data, $attachment) {
            DB::table('data_sets')->where('id', $case->data_set_id)->lockForUpdate()->firstOrFail();
            $case = CommercialCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('chat_messages')->where('event_id', $data['event_id'])->first();
            if ($existing) {
                abort_unless($existing->author_id === $user->id && $existing->commercial_case_id === $case->id && $existing->body === $data['body'], 409);

                return;
            }
            abort_unless($case->status === 'open', 422, 'La oportunidad está cerrada; inicia otra solicitud.');
            if (! empty($data['quote_version'])) {
                QuoteVersion::where('commercial_case_id', $case->id)->where('version', $data['quote_version'])->firstOrFail();
            }
            if ($user->role === 'commerce') {
                $assessment = app(RuleEngine::class)->assess(app(CaseProjector::class)->snapshot($case));
                abort_if(isset($assessment['blocked']['contact']), 422, 'El contacto está restringido por las reglas del caso.');
            }
            $file = $attachment;
            $path = $file ? $file->store('conversations', 'local') : null;
            if ($path && app(DemoContext::class)->loading) {
                app(DemoContext::class)->files[] = $path;
            }
            DB::table('chat_messages')->insert(['event_id' => $data['event_id'], 'commercial_case_id' => $case->id, 'author_id' => $user->id,
                'author_role' => $user->role, 'body' => $data['body'], 'quote_version' => $data['quote_version'] ?? null,
                'attachment_path' => $path,
                'attachment_name' => $file ? basename($file->getClientOriginalName()) : null, 'created_at' => now()]);
            $buyer = $user->role === 'customer';
            app(QuoteService::class)->event($case, $buyer ? 'message.received' : 'action.executed', $buyer ? ['message' => $data['body']] : ['action' => 'contact', 'execution_reference' => 'chat:'.$data['event_id']], $data['event_id']);
            app(CustomerJourney::class)->record($case, $user, $buyer ? 'message.received' : 'message.sent', ['message' => $data['body'], 'version' => $data['quote_version'] ?? null], $data['event_id']);
        }, 3);

    }

    /** @param array<string, mixed> $input */
    public function decision(User $user, CommercialCase $case, array $input): void
    {
        abort_unless(in_array($user->role, ['customer'], true), 403);
        app(CustomerJourney::class)->authorize($user, $case);
        $data = Validator::make($input, ['event_id' => 'required|uuid', 'quote_version' => 'required|integer', 'kind' => ['required', Rule::in(['accept', 'reject', 'change'])],
            'lines' => 'required|array|min:1|max:'.CatalogSelection::MAX_LINES, 'lines.*.line_id' => 'required|string|distinct', 'lines.*.quantity' => 'required|integer|min:0|max:1000000', 'conditions' => 'nullable|string|max:4000'])->validate();
        DB::transaction(function () use ($user, $case, $data) {
            DB::table('data_sets')->where('id', $case->data_set_id)->lockForUpdate()->firstOrFail();
            $case = CommercialCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('buyer_decisions')->where('event_id', $data['event_id'])->first();
            if ($existing) {
                abort_unless($existing->account_id === $user->id && $existing->commercial_case_id === $case->id && $existing->kind === $data['kind']
                    && (int) $existing->quote_version === (int) $data['quote_version'] && json_decode($existing->lines, true) === $data['lines']
                    && $existing->conditions === ($data['conditions'] ?? null), 409);

                return;
            }
            $q = QuoteVersion::where('commercial_case_id', $case->id)->latest('version')->firstOrFail();
            abort_unless($case->status === 'open' && $q->version === (int) $data['quote_version'] && ! $q->valid_until->isPast() && ! $q->getAttribute('withdrawn_at'), 422, 'La versión ya no está vigente.');
            $byId = collect($q->lines)->keyBy('line_id');
            $total = 0;
            foreach ($data['lines'] as $line) {
                abort_unless($byId->has($line['line_id']) && $line['quantity'] <= $byId[$line['line_id']]['quantity'], 422);
                $total += $line['quantity'];
            }
            abort_if($data['kind'] === 'accept' && $total === 0, 422, 'Selecciona al menos una unidad.');
            DB::table('buyer_decisions')->insert([...$data, 'lines' => json_encode($data['lines'], JSON_THROW_ON_ERROR), 'commercial_case_id' => $case->id, 'account_id' => $user->id, 'created_at' => now()]);
            app(CustomerJourney::class)->record($case, $user, 'buyer.'.$data['kind'], $data, $data['event_id']);
            app(QuoteService::class)->event($case, 'message.received', ['message' => 'Decisión estructurada del comprador: '.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        }, 3);

    }

    /** @param array<string, mixed> $input */
    public function result(User $user, CommercialCase $case, array $input): void
    {
        abort_unless(in_array($user->role, ['commerce'], true), 403);
        app(CustomerJourney::class)->authorize($user, $case);
        $data = Validator::make($input, ['event_id' => 'required|uuid', 'kind' => ['required', Rule::in(['formalized', 'payment', 'cancelled', 'rejected'])], 'amount' => 'nullable|decimal:0,2|min:0|max:999999999',
            'reference' => 'required|string|min:5|max:2000', 'occurred_at' => 'required|date', 'close' => 'required|boolean'])->validate();
        $data['occurred_at'] = CarbonImmutable::parse($data['occurred_at'], 'America/Mexico_City')->utc();
        if ($data['occurred_at']->isFuture()) {
            throw ValidationException::withMessages(['occurred_at' => 'La fecha no puede ser futura.']);
        }
        DB::transaction(function () use ($user, $case, $data) {
            DB::table('data_sets')->where('id', $case->data_set_id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('sale_records')->where('event_id', $data['event_id'])->first();
            if ($existing) {
                abort_unless($existing->commercial_case_id === $case->id && $existing->author_id === $user->id
                    && $existing->kind === $data['kind'] && $existing->reference === $data['reference']
                    && $existing->amount_cents === (isset($data['amount']) ? Money::cents($data['amount']) : null)
                    && CarbonImmutable::parse($existing->occurred_at)->equalTo($data['occurred_at']), 409);

                return;
            }
            $amount = isset($data['amount']) ? Money::cents($data['amount']) : null;
            abort_if(in_array($data['kind'], ['formalized', 'payment'], true) && $amount === null, 422);
            $payload = ['kind' => $data['kind'], 'amount_cents' => $amount, 'reference' => $data['reference'], 'occurred_at' => $data['occurred_at']];
            DB::table('sale_records')->insert([...$payload, 'event_id' => $data['event_id'], 'commercial_case_id' => $case->id, 'author_id' => $user->id, 'created_at' => now()]);
            app(CustomerJourney::class)->record($case, $user, 'sale.'.$data['kind'], $payload, $data['event_id']);
            if ($data['close']) {
                abort_unless($case->fresh()->status === 'open', 422);
                app(QuoteService::class)->event($case, 'case.closed', ['status' => match ($data['kind']) {
                    'cancelled' => 'cancelled', 'rejected' => 'rejected', default => 'accepted'
                }]);
            }
        }, 3);

    }

    /** @param array<string, mixed> $input */
    public function withdraw(User $user, CommercialCase $case, array $input): void
    {
        abort_unless(in_array($user->role, ['commerce'], true), 403);
        app(CustomerJourney::class)->authorize($user, $case);
        $data = Validator::make($input, ['version' => 'required|integer', 'reason' => 'required|string|min:5|max:1000'])->validate();
        DB::transaction(function () use ($case, $user, $data) {
            DB::table('data_sets')->where('id', $case->data_set_id)->lockForUpdate()->firstOrFail();
            $case = CommercialCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            abort_unless($case->status === 'open', 422);
            $quote = QuoteVersion::where('commercial_case_id', $case->id)->latest('version')->firstOrFail();
            abort_unless($quote->version === (int) $data['version'], 409);
            if (! $quote->getAttribute('withdrawn_at')) {
                $quote->forceFill(['withdrawn_at' => now()])->save();
                $case->increment('revision');
                $case->update(['next_review_at' => now()]);
                app(CustomerJourney::class)->record($case, $user, 'quote.withdrawn', $data);
            }
        });

    }
}
