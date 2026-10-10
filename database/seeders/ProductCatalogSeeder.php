<?php

namespace Database\Seeders;

use App\Domain\Cases\CatalogManifest;
use App\Domain\Cases\CatalogService;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('role', 'commerce')->orderBy('id')->firstOrFail();
        $this->seedFor($user);
    }

    /** @return array<string, Product> */
    public function seedFor(User $user): array
    {
        $manifest = app(CatalogManifest::class);
        // Image production is independent; retain canonical references for later delivery.
        $manifest->assertImages(false);

        return DB::transaction(function () use ($manifest, $user) {
            DB::table('ai_budget_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
            $products = [];
            foreach ($manifest->styles() as $style) {
                $existing = Product::whereIn('sku', array_column($style['variants'], 'sku'))->get();
                if ($existing->isNotEmpty()) {
                    if ($existing->count() !== 6 || $existing->pluck('style_id')->unique()->count() !== 1) {
                        throw new RuntimeException('Carga parcial o colisión: '.$style['key']);
                    }
                    $variantsBySku = array_column($style['variants'], null, 'sku');
                    foreach ($existing as $product) {
                        $variant = $variantsBySku[$product->sku];
                        foreach (['color', 'size'] as $field) {
                            if ($variant[$field] !== $product->$field) {
                                throw new RuntimeException('SKU con atributos distintos; requiere revisión: '.$product->sku);
                            }
                        }
                        foreach (['gsm', 'composition', 'fit'] as $field) {
                            if ($style[$field] !== $product->$field) {
                                throw new RuntimeException('Ficha editada o colisión; no se sobrescribe: '.$product->sku);
                            }
                        }
                        $products[$product->sku] = $product;
                    }

                    continue;
                }
                if (Product::whereIn('sku', array_column($style['variants'], 'legacy_sku'))->exists() || DB::table('product_styles')->where('name', $style['name'])->exists()) {
                    throw new RuntimeException('Existe catálogo previo. Utiliza byecode:upgrade-catalog.');
                }
                $service = app(CatalogService::class);
                $service->style($user, ['name' => $style['name'], 'category' => $style['category'], 'published' => true, 'details' => $style['details']]);
                $id = DB::table('product_styles')->where('name', $style['name'])->value('id');
                foreach ($style['variants'] as $variant) {
                    $service->variant($user, ['style_id' => $id, 'sku' => $variant['sku'], 'fit' => $style['fit'], 'color' => $variant['color'], 'size' => $variant['size'],
                        'composition' => $style['composition'], 'gsm' => $style['gsm'], 'moq' => $style['moq'], 'source' => 'Ficha comercial v2; medidas orientativas por confirmar en muestra.', 'published' => true, 'details' => $variant['details']]);
                    $products[$variant['sku']] = Product::where('sku', $variant['sku'])->firstOrFail();
                }
            }

            return $products;
        });
    }
}
