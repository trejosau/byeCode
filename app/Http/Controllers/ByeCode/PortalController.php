<?php

namespace App\Http\Controllers\ByeCode;

use App\Domain\Cases\CustomerJourney;
use App\Models\CommercialCase;
use Illuminate\Http\RedirectResponse;

class PortalController
{
    public function show(string $token): RedirectResponse
    {
        abort_unless(preg_match('/^[a-zA-Z0-9]{64}$/D', $token) === 1, 404);
        $case = CommercialCase::where('portal_token_hash', hash('sha256', $token))->where('portal_expires_at', '>', now())->firstOrFail();
        app(CustomerJourney::class)->authorize(request()->user(), $case);
        abort_unless((bool) $case->public_id, 404);

        return redirect('/my/quotes/'.$case->public_id);
    }
}
