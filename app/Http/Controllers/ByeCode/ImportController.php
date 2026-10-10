<?php

namespace App\Http\Controllers\ByeCode;

use App\Domain\Cases\Workspace;
use App\Domain\Evaluation\EvaluationService;
use App\Domain\Events\ImportService;
use App\Http\Controllers\Controller;
use App\Jobs\EvaluateCase;
use App\Models\CommercialCase;
use App\Models\DataSet;
use App\Models\ImportBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ImportController extends Controller
{
    public function index(Request $request): Response
    {
        $sets = DataSet::where('user_id', $request->user()->id)->orderByDesc('id')->get();

        return Inertia::render('byecode/imports', [
            'sets' => $sets, 'batches' => ImportBatch::whereIn('data_set_id', $sets->pluck('id'))->latest('id')->limit(30)->get(['id', 'data_set_id', 'name', 'profile', 'accepted', 'duplicates', 'errors', 'created_at']),
        ]);
    }

    public function kit(Request $request, ImportService $importer): RedirectResponse
    {
        $set = DataSet::create(['user_id' => $request->user()->id, 'name' => 'Kit público · '.now()->format('d/m H:i'), 'mode' => 'kit', 'evaluation_at' => config('byecode.kit_clock')]);
        $importer->import($set, (string) file_get_contents(base_path('referencias/kit_publico/events.jsonl')), 'events.jsonl');

        return redirect('/imports')->with('success', 'Kit inicial cargado: 6 casos, reloj oficial fijo. La actualización aún no se incorporó.');
    }

    public function updateKit(Request $request, DataSet $dataSet, Workspace $workspace, ImportService $importer): RedirectResponse
    {
        $workspace->ownSet($request->user(), $dataSet->id);
        abort_unless($dataSet->mode === 'kit', 422);
        $importer->import($dataSet, (string) file_get_contents(base_path('referencias/kit_publico/updates.jsonl')), 'updates.jsonl');

        return back()->with('success', 'Actualización P06 incorporada. Evalúe otra vez para comparar el cambio.');
    }

    public function store(Request $request, ImportService $importer, Workspace $workspace): RedirectResponse
    {
        $data = $request->validate(['data_set_id' => ['required', 'integer'], 'file' => ['required', 'file', 'max:2048']]);
        $set = $workspace->ownSet($request->user(), (int) $data['data_set_id']);
        abort_unless($set->mode === 'kit', 422, 'Los archivos del reto se cargan en un conjunto de pruebas.');
        $file = $request->file('file');
        try {
            $importer->import($set, (string) file_get_contents($file->getRealPath()), $file->getClientOriginalName());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        return back()->with('success', 'Archivo procesado. Revise aceptados, duplicados y errores por línea.');
    }

    public function evaluate(Request $request, DataSet $dataSet, Workspace $workspace, EvaluationService $evaluations): RedirectResponse
    {
        $workspace->ownSet($request->user(), $dataSet->id);
        foreach (CommercialCase::where('data_set_id', $dataSet->id)->get() as $case) {
            $evaluation = $evaluations->request($case);
            if ($evaluation->status === 'pending') {
                EvaluateCase::dispatch($evaluation->id);
                $evaluation->update(['dispatched_at' => now()]);
            }
        }

        return back()->with('success', 'Evaluaciones en cola. El kit mantiene su reloj fijo.');
    }

    public function export(Request $request, DataSet $dataSet, Workspace $workspace, EvaluationService $evaluations): JsonResponse
    {
        $workspace->ownSet($request->user(), $dataSet->id);

        return DB::transaction(function () use ($dataSet, $evaluations) {
            $set = DataSet::whereKey($dataSet->id)->lockForUpdate()->firstOrFail();
            $cases = CommercialCase::where('data_set_id', $set->id)->orderBy('external_id')->get();
            $rows = [];
            $missing = [];
            foreach ($cases as $case) {
                $rec = $evaluations->current($case);
                if (! $rec || $rec->publication_status === 'rejected') {
                    $missing[] = $case->external_id;
                } else {
                    $rows[] = $rec->result;
                }
            }
            if ($missing || $cases->isEmpty()) {
                return response()->json(['message' => 'No se exportó un resultado incompleto. Hay casos sin recomendación vigente y válida.', 'missing_cases' => $missing, 'coverage' => count($rows), 'total' => $cases->count()], 422);
            }

            return response()->json($rows, 200, [
                'Content-Disposition' => 'attachment; filename="byecode-recommendations-'.$set->id.'.json"',
                'Cache-Control' => 'private, no-store',
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        });
    }
}
