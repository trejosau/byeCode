<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('catalog_upgrades', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('manifest_hash', 64);
            $table->longText('audit');
            $table->timestampTz('created_at');
        });
        $db = DB::class;
        $retired = ['supply_mode', 'stock_units', 'manufacture_units', 'observed_stock', 'stock_verified_at', 'fulfillment_confirmed', 'availability'];
        $clean = function (array $value) use (&$clean, $retired): array {
            foreach ($value as $key => $item) {
                if (in_array($key, $retired, true)) {
                    unset($value[$key]);
                } elseif (is_array($item)) {
                    $value[$key] = $clean($item);
                }
            }

            return $value;
        };
        $audit = [];
        foreach (['products' => 'details', 'quote_versions' => 'lines', 'quote_drafts' => 'payload', 'purchase_requests' => 'lines'] as $table => $column) {
            foreach ($db::table($table)->get() as $row) {
                $before = json_decode($row->$column ?? '[]', true, 512, JSON_THROW_ON_ERROR);
                $after = $clean($before);
                if ($before !== $after || $table === 'products') {
                    $idColumn = $table === 'quote_drafts' ? 'commercial_case_id' : 'id';
                    $audit[] = ['table' => $table, 'id' => $row->$idColumn, 'before' => $before, 'after' => $after, 'retired_stock' => $table === 'products' ? $row->stock : null];
                    $db::table($table)->where($idColumn, $row->$idColumn)->update([$column => json_encode($after, JSON_THROW_ON_ERROR)]);
                }
            }
        }
        $db::table('catalog_upgrades')->insert(['key' => 'retire-inventory-v1', 'manifest_hash' => hash('sha256', json_encode($retired, JSON_THROW_ON_ERROR)),
            'audit' => Crypt::encryptString(json_encode($audit, JSON_THROW_ON_ERROR)), 'created_at' => now()]);
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('stock'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('La restauración del archivo privado requiere revisión; rollback destructivo bloqueado.');
    }
};
