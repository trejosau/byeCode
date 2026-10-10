<?php

namespace App\Http\Controllers\ByeCode;

use App\Domain\Cases\CatalogManifest;
use App\Domain\Cases\CatalogSelection;
use App\Domain\Cases\CatalogService;
use App\Domain\Cases\CommercialPresentation;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController
{
    private const STYLE_DETAILS = ['description', 'fabric', 'construction', 'care', 'fit_guide', 'size_guide', 'photos'];

    public function index(Request $request): Response
    {
        $styles = DB::table('product_styles')->where('published', true)->orderBy('name')->get()->map(function ($row) {
            $row->details = CatalogManifest::visibleDetails(['photos' => [], ...array_intersect_key(json_decode($row->details, true) ?? [], array_flip(self::STYLE_DETAILS))]);

            return ['id' => $row->id, 'name' => $row->name, 'category' => $row->category, 'published' => true, 'details' => $row->details];
        });

        return Inertia::render('journey/catalog', ['styles' => $styles,
            'variants' => Product::where('published', true)->whereHasStylePublished()->get()->map(fn ($p) => [...$p->only(['id', 'style_id', 'name', 'category', 'fit', 'gsm', 'composition', 'color', 'size', 'moq']), 'details' => CatalogManifest::visibleDetails(array_intersect_key($p->details ?? [], array_flip(['color_code', 'measurements', 'tolerance', 'images'])))]),
            'activeStyle' => $request->route('style') ? (int) $request->route('style') : null,
            'selection' => $request->session()->get('selection', [])]);
    }

    public function show(Request $request, int $style): Response
    {
        abort_unless(DB::table('product_styles')->where('id', $style)->where('published', true)->exists(), 404);

        return $this->index($request);
    }

    public function saveSelection(Request $request): RedirectResponse
    {
        $request->validate(['lines' => 'present|array']);
        $request->session()->put('selection', app(CatalogSelection::class)->validate($request->input('lines')));

        return back();
    }

    public function selection(Request $request): RedirectResponse
    {
        $request->validate(['lines' => 'required|array']);
        $request->session()->put('selection', app(CatalogSelection::class)->validate($request->input('lines'), false));
        $destination = $request->user()?->role === 'commerce' ? '/physical-request' : '/request';
        $request->session()->put('url.intended', url($destination));

        return redirect($destination);
    }

    public function manage(): Response
    {
        return Inertia::render('journey/catalog-manage', ['styles' => DB::table('product_styles')->orderBy('name')->get()->map(fn ($s) => [...(array) $s, 'details' => ['photos' => [], ...json_decode($s->details, true)]]),
            'variants' => Product::orderBy('name')->get()->map(fn ($p) => [...$p->toArray(), 'source' => app(CommercialPresentation::class)->text($p->source, $p->demo_run_id !== null)]), 'conflicts' => Product::select('sku')->selectRaw('COUNT(*) as total')->groupBy('sku')->havingRaw('COUNT(*) > 1')->get()]);
    }

    public function style(Request $request): RedirectResponse
    {
        app(CatalogService::class)->style($request->user(), $request->all());

        return back()->with('success', 'Ficha guardada.');
    }

    public function variant(Request $request): RedirectResponse
    {
        app(CatalogService::class)->variant($request->user(), $request->all());

        return back()->with('success', 'Variante guardada.');
    }
}
