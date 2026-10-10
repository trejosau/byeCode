<?php

namespace App\Http\Controllers\ByeCode;

use App\Domain\Evaluation\BudgetLedger;
use App\Http\Controllers\Controller;
use App\Models\AiUsage;
use App\Models\CommercialCase;
use App\Models\DataSet;
use App\Models\Evaluation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntelligenceController extends Controller
{
    public function index(Request $request, BudgetLedger $budget): Response
    {
        $sets = DataSet::where('user_id', $request->user()->id)->pluck('id');
        $cases = CommercialCase::whereIn('data_set_id', $sets)->pluck('id');
        $evaluations = Evaluation::whereIn('commercial_case_id', $cases)->latest('id')->limit(50)->get(['id', 'commercial_case_id', 'status', 'provider', 'model', 'error_message', 'duration_ms', 'created_at']);

        return Inertia::render('byecode/intelligence', [
            'budget' => $budget->summary(), 'model' => config('byecode.model'), 'aiReady' => (bool) config('integrations.openai.api_key'),
            'evaluations' => $evaluations, 'usage' => AiUsage::whereIn('evaluation_id', $evaluations->pluck('id'))->get(['evaluation_id', 'status', 'input_tokens', 'output_tokens', 'reserved_micromxn', 'charged_micromxn']),
        ]);
    }
}
