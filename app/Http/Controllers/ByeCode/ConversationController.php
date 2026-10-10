<?php

namespace App\Http\Controllers\ByeCode;

use App\Domain\Cases\CaseProjector;
use App\Domain\Cases\CustomerJourney;
use App\Domain\Cases\QuoteService;
use App\Domain\Decision\RuleEngine;
use App\Domain\Support\Money;
use App\Models\CommercialCase;
use App\Models\QuoteVersion;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConversationController
{
    public function __construct(private CustomerJourney $journey) {}

    public function show(Request $request, string $publicId): Response
    {
        $case = CommercialCase::where('public_id', $publicId)->firstOrFail();
        $this->journey->authorize($request->user(), $case);
        $quotes = QuoteVersion::where('commercial_case_id', $case->id)->orderByDesc('version')->get()->map(function ($q) {
            $quote = $q->only(['id', 'version', 'subtotal_cents', 'tax_cents', 'shipping_cents', 'discount_cents', 'total_cents', 'terms', 'valid_until', 'created_at', 'withdrawn_at']);
            $quote['lines'] = array_map(fn ($l) => array_intersect_key($l, array_flip(['line_id', 'product_id', 'sku', 'name', 'fit', 'gsm', 'composition', 'color', 'size', 'quantity', 'unit_price_cents', 'total_cents', 'supply_mode'])), $q->lines);

            return $quote;
        });
        $submission = DB::table('purchase_requests')->where('commercial_case_id', $case->id)->first();

        return Inertia::render('journey/quote', [
            'case' => $case->only(['id', 'public_id', 'title', 'status', 'external_id']), 'quotes' => $quotes,
            'requestLines' => $submission ? json_decode($submission->lines, true) : [],
            'requestMessage' => $submission?->message, 'desiredDate' => $submission?->desired_date,
            'messages' => DB::table('chat_messages')->join('users', 'users.id', '=', 'chat_messages.author_id')->where('commercial_case_id', $case->id)->orderBy('chat_messages.id')->get(['chat_messages.id', 'event_id', 'body', 'quote_version', 'author_role', 'attachment_name', 'chat_messages.created_at', 'users.name'])
                ->map(fn ($row) => [...(array) $row, 'created_at' => CarbonImmutable::parse($row->created_at, 'UTC')->toIso8601ZuluString()]),
            'decisions' => DB::table('buyer_decisions')->where('commercial_case_id', $case->id)->orderBy('id')->get()->map(fn ($r) => [...(array) $r, 'lines' => json_decode($r->lines, true)]),
            'commerce' => $request->user()->role === 'commerce', 'readOnly' => $request->user()->role === 'admin',
        ]);
    }

    public function message(Request $request, CommercialCase $case): RedirectResponse
    {
        $this->journey->authorize($request->user(), $case);
        $data = $request->validate(['event_id' => 'required|uuid', 'body' => 'required|string|min:1|max:8000', 'quote_version' => 'nullable|integer', 'attachment' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);
        DB::transaction(function () use ($request, $case, $data) {
            DB::table('data_sets')->where('id', $case->data_set_id)->lockForUpdate()->firstOrFail();
            $case = CommercialCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('chat_messages')->where('event_id', $data['event_id'])->first();
            if ($existing) {
                abort_unless($existing->author_id === $request->user()->id && $existing->commercial_case_id === $case->id && $existing->body === $data['body'], 409);

                return;
            }
            abort_unless($case->status === 'open', 422, 'La oportunidad está cerrada; inicia otra solicitud.');
            if (! empty($data['quote_version'])) {
                QuoteVersion::where('commercial_case_id', $case->id)->where('version', $data['quote_version'])->firstOrFail();
            }
            if ($request->user()->role === 'commerce') {
                $assessment = app(RuleEngine::class)->assess(app(CaseProjector::class)->snapshot($case));
                abort_if($assessment['contact_prohibited'], 422, 'El contacto está restringido por las reglas del caso.');
            }
            $file = $request->file('attachment');
            abort_if(is_array($file), 422);
            DB::table('chat_messages')->insert(['event_id' => $data['event_id'], 'commercial_case_id' => $case->id, 'author_id' => $request->user()->id,
                'author_role' => $request->user()->role, 'body' => $data['body'], 'quote_version' => $data['quote_version'] ?? null,
                'attachment_path' => $file ? $file->store('conversations', 'local') : null,
                'attachment_name' => $file ? basename($file->getClientOriginalName()) : null, 'created_at' => now()]);
            $buyer = $request->user()->role === 'customer';
            app(QuoteService::class)->event($case, $buyer ? 'message.received' : 'action.executed', $buyer ? ['message' => $data['body']] : ['action' => 'contact', 'execution_reference' => 'chat:'.$data['event_id']], $data['event_id']);
            $this->journey->record($case, $request->user(), $buyer ? 'message.received' : 'message.sent', ['message' => $data['body'], 'version' => $data['quote_version'] ?? null], $data['event_id']);
        }, 3);

        return back()->with('success', 'Mensaje guardado.');
    }

    public function attachment(Request $request, int $id): StreamedResponse
    {
        $row = DB::table('chat_messages')->where('id', $id)->firstOrFail();
        abort_unless((bool) $row->attachment_path, 404);
        $this->journey->authorize($request->user(), CommercialCase::whereKey($row->commercial_case_id)->firstOrFail());

        return Storage::disk('local')->download($row->attachment_path, $row->attachment_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function decision(Request $request, CommercialCase $case): RedirectResponse
    {
        $this->journey->authorize($request->user(), $case);
        $data = $request->validate(['event_id' => 'required|uuid', 'quote_version' => 'required|integer', 'kind' => ['required', Rule::in(['accept', 'reject', 'change'])],
            'lines' => 'required|array|min:1|max:30', 'lines.*.line_id' => 'required|string|distinct', 'lines.*.quantity' => 'required|integer|min:0|max:1000000', 'conditions' => 'nullable|string|max:4000']);
        DB::transaction(function () use ($request, $case, $data) {
            DB::table('data_sets')->where('id', $case->data_set_id)->lockForUpdate()->firstOrFail();
            $case = CommercialCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('buyer_decisions')->where('event_id', $data['event_id'])->first();
            if ($existing) {
                abort_unless($existing->account_id === $request->user()->id && $existing->commercial_case_id === $case->id && $existing->kind === $data['kind']
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
            DB::table('buyer_decisions')->insert([...$data, 'lines' => json_encode($data['lines'], JSON_THROW_ON_ERROR), 'commercial_case_id' => $case->id, 'account_id' => $request->user()->id, 'created_at' => now()]);
            $this->journey->record($case, $request->user(), 'buyer.'.$data['kind'], $data, $data['event_id']);
            app(QuoteService::class)->event($case, 'message.received', ['message' => 'Decisión estructurada del comprador: '.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        }, 3);

        return back()->with('success', 'Decisión registrada. Comercio validará las condiciones y pendientes.');
    }

    public function result(Request $request, CommercialCase $case): RedirectResponse
    {
        $this->journey->authorize($request->user(), $case);
        $data = $request->validate(['event_id' => 'required|uuid', 'kind' => ['required', Rule::in(['formalized', 'payment', 'cancelled', 'rejected'])], 'amount' => 'nullable|decimal:0,2|min:0|max:999999999',
            'reference' => 'required|string|min:5|max:2000', 'occurred_at' => 'required|date', 'close' => 'required|boolean']);
        $data['occurred_at'] = CarbonImmutable::parse($data['occurred_at'], 'America/Mexico_City')->utc();
        if ($data['occurred_at']->isFuture()) {
            throw ValidationException::withMessages(['occurred_at' => 'La fecha no puede ser futura.']);
        }
        DB::transaction(function () use ($request, $case, $data) {
            DB::table('data_sets')->where('id', $case->data_set_id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('sale_records')->where('event_id', $data['event_id'])->first();
            if ($existing) {
                abort_unless($existing->commercial_case_id === $case->id && $existing->author_id === $request->user()->id
                    && $existing->kind === $data['kind'] && $existing->reference === $data['reference']
                    && $existing->amount_cents === (isset($data['amount']) ? Money::cents($data['amount']) : null)
                    && CarbonImmutable::parse($existing->occurred_at)->equalTo($data['occurred_at']), 409);

                return;
            }
            $amount = isset($data['amount']) ? Money::cents($data['amount']) : null;
            abort_if(in_array($data['kind'], ['formalized', 'payment'], true) && $amount === null, 422);
            $payload = ['kind' => $data['kind'], 'amount_cents' => $amount, 'reference' => $data['reference'], 'occurred_at' => $data['occurred_at']];
            DB::table('sale_records')->insert([...$payload, 'event_id' => $data['event_id'], 'commercial_case_id' => $case->id, 'author_id' => $request->user()->id, 'created_at' => now()]);
            $this->journey->record($case, $request->user(), 'sale.'.$data['kind'], $payload, $data['event_id']);
            if ($data['close']) {
                abort_unless($case->fresh()->status === 'open', 422);
                app(QuoteService::class)->event($case, 'case.closed', ['status' => match ($data['kind']) {
                    'cancelled' => 'cancelled', 'rejected' => 'rejected', default => 'accepted'
                }]);
            }
        }, 3);

        return back()->with('success', 'Resultado registrado con evidencia.');
    }

    public function withdraw(Request $request, CommercialCase $case): RedirectResponse
    {
        $this->journey->authorize($request->user(), $case);
        $data = $request->validate(['version' => 'required|integer', 'reason' => 'required|string|min:5|max:1000']);
        DB::transaction(function () use ($case, $request, $data) {
            DB::table('data_sets')->where('id', $case->data_set_id)->lockForUpdate()->firstOrFail();
            $case = CommercialCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            abort_unless($case->status === 'open', 422);
            $quote = QuoteVersion::where('commercial_case_id', $case->id)->latest('version')->firstOrFail();
            abort_unless($quote->version === (int) $data['version'], 409);
            if (! $quote->getAttribute('withdrawn_at')) {
                $quote->forceFill(['withdrawn_at' => now()])->save();
                $case->increment('revision');
                $case->update(['next_review_at' => now()]);
                $this->journey->record($case, $request->user(), 'quote.withdrawn', $data);
            }
        });

        return back()->with('success', 'Propuesta retirada. El historial se conserva.');
    }
}
