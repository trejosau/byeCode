<?php

namespace App\Domain\Cases;

use App\Domain\Demo\DemoScenarioLoader;
use App\Models\Product;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class CatalogUpgrade
{
    public const KEY = 'catalog-v2';

    /** @return array<string, mixed> */
    public function run(bool $dryRun = true): array
    {
        return DB::transaction(function () use ($dryRun) {
            DB::table('ai_budget_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
            $manifest = app(CatalogManifest::class);
            $installed = Schema::hasTable('catalog_upgrades') ? DB::table('catalog_upgrades')->where('key', self::KEY)->first() : null;
            if ($installed) {
                if ($installed->manifest_hash !== $manifest->hash()) {
                    throw new RuntimeException('Cambió el manifiesto aplicado. Se requiere otra versión.');
                }
                $audit = json_decode(Crypt::decryptString($installed->audit), true, 512, JSON_THROW_ON_ERROR);
                $missing = Product::whereIn('id', array_column($audit['mapping'], 'id'))->count() !== 36;

                return ['already_applied' => true, 'preserved_later_edits' => true, 'conflicts' => $missing ? ['Faltan productos registrados.'] : [], 'image_errors' => $manifest->imageErrors()];
            }
            $conflicts = [];
            $mapping = [];
            $originals = json_decode((string) file_get_contents(database_path('seeders/fixtures/operational-demo-v1/catalog.json')), true, 512, JSON_THROW_ON_ERROR);
            $run = DB::table('demo_runs')->where('key', 'operational-demo-v1')->first();
            if (! $run || $run->status !== 'ready' || $run->manifest_hash !== app(DemoScenarioLoader::class)->forVersion('operational-demo-v1')->hash()) {
                $conflicts[] = 'La ejecución v1 esperada no está lista o su hash difiere.';
            }
            foreach ($manifest->styles() as $index => $style) {
                foreach ($style['variants'] as $variant) {
                    $product = Product::where('sku', $variant['legacy_sku'])->lockForUpdate()->first();
                    if (! $product) {
                        $conflicts[] = 'Sin correspondencia: '.$variant['legacy_sku'];

                        continue;
                    }
                    $oldStyle = DB::table('product_styles')->where('id', $product->style_id)->first();
                    // PostgreSQL JSONB sorts object keys; compare content, not serialization order.
                    if (! $oldStyle || $oldStyle->name !== $originals[$index]['name'] || json_decode($oldStyle->details, true) != $originals[$index]['details']) {
                        $conflicts[] = 'Ficha editada que requiere revisión: '.$product->style_id;
                    }
                    foreach (['gsm', 'composition', 'fit', 'moq', 'name', 'category'] as $field) {
                        if ($originals[$index][$field] !== $product->$field) {
                            $conflicts[] = 'Edición que requiere revisión: '.$product->id.' / '.$field;
                        }
                    }
                    $expectedDetails = ['color_code' => $variant['color'] === 'Negro' ? '#171717' : '#eee9dc',
                        'measurements' => 'Pecho/largo en plano: S 50/68, M 53/71, L 56/74 cm.', 'tolerance' => '±1.5 cm; confirmar muestra.'];
                    $details = array_diff_key($product->details ?? [], ['availability' => true]);
                    if ($details != $expectedDetails || ! $product->published || ! $oldStyle?->published) {
                        $conflicts[] = 'Medidas, imágenes o publicación editadas: '.$product->id;
                    }
                    if ($product->color !== $variant['color'] || $product->size !== $variant['size'] || $product->demo_run_id !== $run?->id || Product::where('sku', $variant['sku'])->exists()) {
                        $conflicts[] = 'Colisión o atributos distintos: '.$variant['sku'];
                    }
                    $mapping[] = ['id' => $product->id, 'style_id' => $product->style_id, 'old' => $variant['legacy_sku'], 'new' => $variant['sku'], 'style' => $style['key']];
                }
            }
            $report = ['dry_run' => $dryRun, 'mapping' => $mapping, 'conflicts' => array_values(array_unique($conflicts)), 'image_errors' => $manifest->imageErrors(), 'manifest_hash' => $manifest->hash()];
            if ($dryRun) {
                return $report;
            }
            $manifest->assertImages(false);
            if ($conflicts) {
                throw new RuntimeException('Actualización bloqueada: '.implode('; ', $conflicts));
            }
            $before = $this->economics();
            $audit = ['mapping' => $mapping, 'before' => [], 'economics_before' => $before];
            foreach ($manifest->styles() as $style) {
                $rows = array_values(array_filter($mapping, fn ($m) => $m['style'] === $style['key']));
                $styleId = $rows[0]['style_id'];
                $oldStyle = DB::table('product_styles')->where('id', $styleId)->firstOrFail();
                $audit['before']['styles'][] = (array) $oldStyle;
                DB::table('product_styles')->where('id', $styleId)->update(['name' => $style['name'], 'category' => $style['category'], 'details' => json_encode($style['details'], JSON_THROW_ON_ERROR)]);
                foreach ($style['variants'] as $variant) {
                    $product = Product::where('sku', $variant['legacy_sku'])->firstOrFail();
                    $audit['before']['products'][] = $product->toArray();
                    DB::table('products')->where('id', $product->id)->update(['sku' => $variant['sku'], 'name' => $style['name'], 'category' => $style['category'], 'fit' => $style['fit'], 'details' => json_encode($variant['details'], JSON_THROW_ON_ERROR)]);
                }
            }
            foreach (['purchase_requests' => 'lines', 'quote_versions' => 'lines', 'quote_drafts' => 'payload'] as $table => $column) {
                foreach (DB::table($table)->lockForUpdate()->get() as $row) {
                    $payload = json_decode($row->$column, true, 512, JSON_THROW_ON_ERROR);
                    $lines = $table === 'quote_drafts' ? ($payload['lines'] ?? []) : $payload;
                    $changed = false;
                    foreach ($lines as &$line) {
                        $match = collect($mapping)->firstWhere('id', (int) ($line['product_id'] ?? 0));
                        if (! $match) {
                            continue;
                        }
                        $product = Product::findOrFail($match['id']);
                        foreach (['color', 'size', 'gsm', 'composition'] as $field) {
                            if (isset($line[$field]) && $line[$field] !== $product->$field) {
                                throw new RuntimeException('Referencia histórica sin correspondencia exacta: '.$table);
                            }
                        }
                        if ($table !== 'quote_drafts') {
                            $line['sku'] = $product->sku;
                            $line['name'] = $product->name;
                            $line['fit'] = $product->fit;
                            $line['color_code'] = $product->details['color_code'];
                            $line['images'] = $product->details['images'];
                            $changed = true;
                        }
                    }
                    unset($line);
                    if (! $changed) {
                        continue;
                    }
                    $idColumn = $table === 'quote_drafts' ? 'commercial_case_id' : 'id';
                    $audit['before'][$table][] = ['id' => $row->$idColumn, $column => $payload];
                    if ($table === 'quote_drafts') {
                        $payload['lines'] = $lines;
                    } else {
                        $payload = $lines;
                    }
                    DB::table($table)->where($idColumn, $row->$idColumn)->update([$column => json_encode($payload, JSON_THROW_ON_ERROR)]);
                }
            }
            if ($before !== $this->economics()) {
                throw new RuntimeException('Cambió un importe o relación. Actualización revertida.');
            }
            $audit['economics_after'] = $this->economics();
            DB::table('catalog_upgrades')->insert(['key' => self::KEY, 'manifest_hash' => $manifest->hash(), 'audit' => Crypt::encryptString(json_encode($audit, JSON_THROW_ON_ERROR)), 'created_at' => now()]);

            return [...$report, 'applied' => true, 'economics_preserved' => true];
        });
    }

    /** @return array<string, mixed> */
    private function economics(): array
    {
        $result = [];
        foreach (['users', 'customers', 'commercial_cases', 'quote_versions', 'sale_records', 'chat_messages', 'purchase_requests'] as $table) {
            $result[$table] = DB::table($table)->orderBy('id')->pluck('id')->all();
        }
        $result['quote_amounts'] = DB::table('quote_versions')->orderBy('id')->get(['id', 'commercial_case_id', 'version', 'subtotal_cents', 'tax_cents', 'shipping_cents', 'discount_cents', 'total_cents'])->map(fn ($r) => (array) $r)->all();
        $result['payments'] = DB::table('sale_records')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

        return $result;
    }
}
