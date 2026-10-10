<?php

namespace App\Http\Controllers\ByeCode;

use App\Domain\Cases\CustomerJourney;
use App\Domain\Cases\DraftService;
use App\Models\CommercialCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DraftController
{
    public function store(Request $request, CommercialCase $case, CustomerJourney $journey): RedirectResponse
    {
        app(DraftService::class)->store($request->user(), $case, $request->all());

        return back()->with('success', 'Borrador guardado. Todavía no es visible al cliente.');
    }
}
