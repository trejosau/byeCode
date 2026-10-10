<?php

namespace App\Domain\Cases;

use App\Models\CommercialCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class DraftService
{
    /** @param array<string, mixed> $input */
    public function store(User $user, CommercialCase $case, array $input): void
    {
        abort_unless(in_array($user->role, ['commerce'], true), 403);
        app(CustomerJourney::class)->authorize($user, $case);
        $data = Validator::make($input, ['draft_revision' => 'required|integer|min:0', 'title' => 'nullable|string|max:180', 'customer_id' => 'required|integer',
            'expected_revision' => 'required|integer', 'valid_until' => 'nullable|string|max:30', 'terms' => 'nullable|string|max:4000', 'authorization_reference' => 'nullable|string|max:1000',
            'tax' => 'nullable|string|max:20', 'shipping' => 'nullable|string|max:20', 'discount' => 'nullable|string|max:20',
            'lines' => 'present|array|max:'.CatalogSelection::MAX_LINES, 'lines.*' => 'required|array:product_id,quantity,unit_price',
            'lines.*.product_id' => 'nullable|string|max:20|distinct', 'lines.*.quantity' => 'nullable|string|max:20', 'lines.*.unit_price' => 'nullable|string|max:20'])->validate();
        DB::transaction(function () use ($case, $data) {
            $case = CommercialCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            abort_unless($case->status === 'open' && $case->customer_id === (int) $data['customer_id'], 422);
            abort_unless($case->revision === (int) $data['expected_revision'], 409, 'El caso cambió. Recarga antes de guardar.');
            $draft = DB::table('quote_drafts')->where('commercial_case_id', $case->id)->first();
            abort_unless(($draft ? (int) $draft->revision : 0) === (int) $data['draft_revision'], 409, 'Otro comercial modificó el borrador. Recarga.');
            DB::table('quote_drafts')->updateOrInsert(['commercial_case_id' => $case->id], ['payload' => json_encode($data, JSON_THROW_ON_ERROR), 'revision' => $data['draft_revision'] + 1, 'updated_at' => now(), 'created_at' => $draft ? $draft->created_at : now()]);
        });

    }
}
