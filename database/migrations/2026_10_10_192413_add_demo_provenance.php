<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('demo_runs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('key')->unique();
            $t->string('version');
            $t->unsignedInteger('seed');
            $t->timestampTz('reference_at');
            $t->timestampTz('loaded_at');
            $t->string('manifest_hash');
            $t->string('status');
            $t->string('semantic_policy')->default('rules_only');
            $t->jsonb('report')->nullable();
            $t->timestampsTz();
        });
        Schema::create('demo_records', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('demo_run_id')->constrained('demo_runs')->restrictOnDelete();
            $t->string('entity_type');
            $t->string('entity_id');
            $t->string('logical_key');
            $t->string('baseline_hash');
            $t->unique(['demo_run_id', 'logical_key']);
            $t->unique(['entity_type', 'entity_id']);
        });
        foreach (['users', 'customers', 'commercial_cases', 'product_styles', 'products', 'mail_deliveries'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->foreignUuid('demo_run_id')->nullable()->index()->constrained('demo_runs')->restrictOnDelete());
        }
        Schema::table('profile_facts', function (Blueprint $t) {
            $t->foreignId('author_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('origin')->default('application');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('La procedencia y el historial se conservan; use una migración correctiva.');
    }
};
