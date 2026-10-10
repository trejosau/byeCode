<?php

namespace App\Http\Controllers\ByeCode;

use App\Domain\Cases\CustomerJourney;
use App\Models\CommercialCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DraftController
{
    public function store(Request $request, CommercialCase $case, CustomerJourney $journey): RedirectResponse
    {
        $journey->authorize($request->user(), $case);
        $data = $request->validate(['draft_revision' => 'required|integer|min:0', 'title' => 'nullable|string|max:180', 'customer_id' => 'required|integer',
            'expected_revision' => 'required|integer', 'valid_until' => 'nullable|string|max:30', 'terms' => 'nullable|string|max:4000', 'authorization_reference' => 'nullable|string|max:1000',
            'tax' => 'nullable|string|max:20', 'shipping' => 'nullable|string|max:20', 'discount' => 'nullable|string|max:20',
            'lines' => 'present|array|max:30', 'lines.*' => 'required|array:product_id,quantity,unit_price,supply_mode,stock_units',
            'lines.*.product_id' => 'nullable|string|max:20', 'lines.*.quantity' => 'nullable|string|max:20', 'lines.*.unit_price' => 'nullable|string|max:20', 'lines.*.supply_mode' => 'nullable|string|max:20', 'lines.*.stock_units' => 'nullable|string|max:20']);
        DB::transaction(function () use ($case, $data) {
            $case = CommercialCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            abort_unless($case->status === 'open' && $case->customer_id === (int) $data['customer_id'], 422);
            abort_unless($case->revision === (int) $data['expected_revision'], 409, 'El caso cambió. Recarga antes de guardar.');
            $draft = DB::table('quote_drafts')->where('commercial_case_id', $case->id)->first();
            abort_unless(($draft ? (int) $draft->revision : 0) === (int) $data['draft_revision'], 409, 'Otro comercial modificó el borrador. Recarga.');
            DB::table('quote_drafts')->updateOrInsert(['commercial_case_id' => $case->id], ['payload' => json_encode($data, JSON_THROW_ON_ERROR), 'revision' => $data['draft_revision'] + 1, 'updated_at' => now(), 'created_at' => $draft ? $draft->created_at : now()]);
        });

        return back()->with('success', 'Borrador guardado. Todavía no es visible al cliente.');
    }
}
