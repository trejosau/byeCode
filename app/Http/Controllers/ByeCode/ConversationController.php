<?php

namespace App\Http\Controllers\ByeCode;

use App\Domain\Cases\ConversationService;
use App\Domain\Cases\CustomerJourney;
use App\Models\CommercialCase;
use App\Models\QuoteVersion;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            'case' => $case->only(['id', 'public_id', 'title', 'status', 'external_id', 'demo_run_id']), 'quotes' => $quotes,
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
        $request->validate(['attachment' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);
        app(ConversationService::class)->message($request->user(), $case, array_diff_key($request->all(), ['attachment' => true]), $request->file('attachment'));

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
        app(ConversationService::class)->decision($request->user(), $case, $request->all());

        return back()->with('success', 'Decisión registrada. Comercio validará las condiciones y pendientes.');
    }

    public function result(Request $request, CommercialCase $case): RedirectResponse
    {
        app(ConversationService::class)->result($request->user(), $case, $request->all());

        return back()->with('success', 'Resultado registrado con evidencia.');
    }

    public function withdraw(Request $request, CommercialCase $case): RedirectResponse
    {
        app(ConversationService::class)->withdraw($request->user(), $case, $request->all());

        return back()->with('success', 'Propuesta retirada. El historial se conserva.');
    }
}
