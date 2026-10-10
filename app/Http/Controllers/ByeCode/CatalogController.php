<?php

namespace App\Http\Controllers\ByeCode;

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
        $data = $request->validate(['id' => 'nullable|integer|exists:product_styles,id', 'name' => 'required|string|max:180', 'category' => 'required|string|max:80', 'published' => 'required|boolean',
            'details' => 'required|array:description,fabric,construction,care,fit_guide,size_guide,photos',
            'details.description' => 'nullable|string|max:5000', 'details.fabric' => 'nullable|string|max:1000', 'details.construction' => 'nullable|string|max:2000',
            'details.care' => 'nullable|string|max:1000', 'details.fit_guide' => 'nullable|string|max:4000', 'details.size_guide' => 'nullable|string|max:4000',
            'details.photos' => 'present|array|max:8', 'details.photos.*' => 'required|url:https|max:1000']);
        $id = $data['id'] ?? null;
        $values = ['name' => $data['name'], 'category' => $data['category'], 'details' => json_encode($data['details'], JSON_THROW_ON_ERROR), 'published' => $data['published'], 'updated_at' => now()];
        if ($id) {
            DB::table('product_styles')->where('id', $id)->update($values);
        } else {
            DB::table('product_styles')->insert([...$values, 'created_at' => now()]);
        }

        return back()->with('success', 'Ficha guardada.');
    }

    public function variant(Request $request): RedirectResponse
    {
        $data = $request->validate(['id' => 'nullable|integer|exists:products,id', 'style_id' => 'required|integer|exists:product_styles,id', 'sku' => 'required|string|max:80',
            'fit' => 'required|string|max:80', 'color' => 'required|string|max:80', 'size' => 'required|string|max:30', 'composition' => 'nullable|string|max:180',
            'gsm' => 'nullable|integer|min:1|max:1500', 'moq' => 'nullable|integer|min:1', 'stock' => 'nullable|integer|min:0',
            'source' => 'required|string|max:500', 'published' => 'required|boolean', 'details' => 'required|array:color_code,measurements,tolerance,availability',
            'details.color_code' => 'nullable|string|max:80', 'details.measurements' => 'nullable|string|max:1000', 'details.tolerance' => 'nullable|string|max:500', 'details.availability' => 'nullable|string|max:500']);
        DB::transaction(function () use ($data, $request) {
            // Serialize catalog edits, including global SKU and combination uniqueness.
            DB::table('ai_budget_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
            $id = $data['id'] ?? null;
            abort_if(Product::where('sku', $data['sku'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists(), 422, 'SKU ya registrado; revisa conflictos.');
            abort_if(Product::where('style_id', $data['style_id'])->where('fit', $data['fit'])->where('color', $data['color'])->where('size', $data['size'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists(), 422, 'La combinación ya existe.');
            $style = DB::table('product_styles')->where('id', $data['style_id'])->firstOrFail();
            unset($data['id']);
            $values = [...$data, 'name' => $style->name, 'category' => $style->category, 'verified_at' => now()];
            if ($id) {
                Product::whereKey($id)->firstOrFail()->update($values);
            } else {
                Product::create([...$values, 'user_id' => $request->user()->id]);
            }
        });

        return back()->with('success', 'Variante guardada.');
    }
}
