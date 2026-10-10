<?php

namespace App\Http\Controllers\ByeCode;

use App\Domain\Prediction\BusinessHistory;
use App\Domain\Prediction\PredictionService;
use App\Http\Controllers\Controller;
use App\Models\PredictionRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PredictionController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('byecode/prediction', ['runs' => PredictionRun::latest('id')->limit(20)->get(['id', 'status', 'data_kind', 'report', 'dataset_hash', 'created_at'])]);
    }

    public function train(Request $request, PredictionService $service): RedirectResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'max:2048'], 'data_kind' => ['required', Rule::in(['real', 'synthetic'])], 'authorized' => ['accepted']]);
        $file = $request->file('file');
        try {
            $service->train($request->user(), (string) file_get_contents($file->getRealPath()), $data['data_kind']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        return back()->with('success', 'Entrenamiento y evaluación terminados. Revisa las particiones, métricas y criterios antes de usar estimaciones.');
    }

    public function template(): HttpResponse
    {
        return response(implode(',', PredictionService::COLUMNS)."\n", 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="byecode-historical-template.csv"']);
    }

    public function history(Request $request): RedirectResponse
    {
        app(BusinessHistory::class)->train($request->user());

        return back()->with('success', 'Historial evaluado localmente. Revisa la madurez de los datos y los criterios de validación.');
    }
}
