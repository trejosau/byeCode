<?php

namespace App\Http\Controllers\ByeCode;

use App\Domain\Cases\CatalogService;
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
            $row->details = ['photos' => [], ...array_intersect_key(json_decode($row->details, true) ?? [], array_flip(self::STYLE_DETAILS))];

            return $row;
        });

        return Inertia::render('journey/catalog', ['styles' => $styles,
            'variants' => Product::where('published', true)->whereHasStylePublished()->get(['id', 'style_id', 'sku', 'name', 'category', 'fit', 'gsm', 'composition', 'color', 'size', 'moq', 'stock', 'verified_at', 'details']),
            'selection' => $request->session()->get('selection', [])]);
    }

    public function selection(Request $request): RedirectResponse
    {
        $data = $request->validate(['lines' => 'present|array|max:30', 'lines.*.product_id' => 'required|integer|distinct|exists:products,id', 'lines.*.quantity' => 'required|integer|min:1|max:1000000']);
        $request->session()->put('selection', $data['lines']);
        $destination = $request->user()?->role === 'commerce' ? '/physical-request' : '/request';
        $request->session()->put('url.intended', url($destination));

        return redirect($destination);
    }

    public function manage(): Response
    {
        return Inertia::render('journey/catalog-manage', ['styles' => DB::table('product_styles')->orderBy('name')->get()->map(fn ($s) => [...(array) $s, 'details' => ['photos' => [], ...json_decode($s->details, true)]]),
            'variants' => Product::orderBy('name')->get(), 'conflicts' => Product::select('sku')->selectRaw('COUNT(*) as total')->groupBy('sku')->havingRaw('COUNT(*) > 1')->get()]);
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
