<?php

namespace App\Domain\Cases;

use App\Domain\Demo\DemoContext;
use App\Models\Product;
use App\Models\User;
use App\Rules\CatalogImagePath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class CatalogService
{
    /** @param array<string, mixed> $input */
    public function style(User $user, array $input): void
    {
        abort_unless(in_array($user->role, ['commerce'], true), 403);
        $data = Validator::make($input, ['id' => 'nullable|integer|exists:product_styles,id', 'name' => 'required|string|max:180', 'category' => 'required|string|max:80', 'published' => 'required|boolean',
            'details' => 'required|array:description,fabric,construction,care,fit_guide,size_guide,photos',
            'details.description' => 'nullable|string|max:5000', 'details.fabric' => 'nullable|string|max:1000', 'details.construction' => 'nullable|string|max:2000',
            'details.care' => 'nullable|string|max:1000', 'details.fit_guide' => 'nullable|string|max:4000', 'details.size_guide' => 'nullable|string|max:4000',
            'details.photos' => 'present|array|max:8', 'details.photos.*' => ['required', 'string', 'max:1000', new CatalogImagePath]])->validate();
        $id = $data['id'] ?? null;
        $values = ['name' => $data['name'], 'category' => $data['category'], 'details' => json_encode($data['details'], JSON_THROW_ON_ERROR), 'published' => $data['published'], 'updated_at' => now()];
        if ($id) {
            DB::table('product_styles')->where('id', $id)->update($values);
        } else {
            DB::table('product_styles')->insert([...$values, 'demo_run_id' => app(DemoContext::class)->runId, 'created_at' => now()]);
        }

    }

    /** @param array<string, mixed> $input */
    public function variant(User $user, array $input): void
    {
        abort_unless(in_array($user->role, ['commerce'], true), 403);
        $data = Validator::make($input, ['id' => 'nullable|integer|exists:products,id', 'style_id' => 'required|integer|exists:product_styles,id', 'sku' => 'required|string|max:80',
            'fit' => 'required|string|max:80', 'color' => 'required|string|max:80', 'size' => 'required|string|max:30', 'composition' => 'nullable|string|max:180',
            'gsm' => 'nullable|integer|min:1|max:1500', 'moq' => 'nullable|integer|min:1',
            'source' => 'required|string|max:500', 'published' => 'required|boolean', 'details' => 'required|array:color_code,measurements,tolerance,images',
            'details.color_code' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/D'], 'details.measurements' => 'nullable|string|max:1000', 'details.tolerance' => 'nullable|string|max:500',
            'details.images' => 'sometimes|array|max:8', 'details.images.*' => 'array:view,path,alt,width,height',
            'details.images.*.view' => 'required|in:front,back,three-quarter,detail', 'details.images.*.path' => ['required', new CatalogImagePath],
            'details.images.*.alt' => 'required|string|max:250', 'details.images.*.width' => 'required|integer|min:1|max:8192', 'details.images.*.height' => 'required|integer|min:1|max:8192'])->validate();
        foreach (['fit', 'color', 'size', 'composition'] as $field) {
            $data[$field] = isset($data[$field]) ? preg_replace('/\s+/u', ' ', trim($data[$field])) : null;
        }
        $data['sku'] = strtoupper(trim($data['sku']));
        DB::transaction(function () use ($data, $user) {
            // Serialize catalog edits, including global SKU and combination uniqueness.
            DB::table('ai_budget_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
            $id = $data['id'] ?? null;
            abort_if(Product::where('sku', $data['sku'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists(), 422, 'SKU ya registrado; revisa conflictos.');
            $combination = Product::where('style_id', $data['style_id'])->where('gsm', $data['gsm'] ?? null)->when($id, fn ($q) => $q->where('id', '!=', $id));
            foreach (['fit', 'color', 'size', 'composition'] as $field) {
                $combination->whereRaw('LOWER(TRIM(COALESCE('.$field.", ''))) = ?", [mb_strtolower($data[$field] ?? '')]);
            }
            abort_if($combination->exists(), 422, 'La combinación ya existe.');
            $style = DB::table('product_styles')->where('id', $data['style_id'])->firstOrFail();
            unset($data['id']);
            $values = [...$data, 'name' => $style->name, 'category' => $style->category, 'verified_at' => now()];
            if ($id) {
                Product::whereKey($id)->firstOrFail()->update($values);
            } else {
                Product::create([...$values, 'demo_run_id' => $style->demo_run_id, 'user_id' => $user->id]);
            }
        });

    }
}
