<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('data_sets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('name');
            $t->string('mode');
            $t->timestampTz('evaluation_at')->nullable();
            $t->boolean('integrity_blocked')->default(false);
            $t->timestampsTz();
            $t->index(['user_id', 'mode']);
        });
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('company');
            $t->string('contact_name')->nullable();
            $t->string('email')->nullable();
            $t->text('notes')->nullable();
            $t->timestampsTz();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('sku');
            $t->string('name');
            $t->string('category');
            $t->string('fit')->nullable();
            $t->unsignedInteger('gsm')->nullable();
            $t->string('composition')->nullable();
            $t->string('color')->nullable();
            $t->string('size')->nullable();
            $t->unsignedBigInteger('unit_price_cents')->nullable();
            $t->unsignedInteger('moq')->nullable();
            $t->unsignedInteger('stock')->nullable();
            $t->timestampTz('verified_at')->nullable();
            $t->text('source')->nullable();
            $t->timestampsTz();
            $t->unique(['user_id', 'sku']);
        });
        Schema::create('commercial_cases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('data_set_id')->constrained('data_sets')->cascadeOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->string('external_id');
            $t->string('title');
            $t->string('status')->default('open');
            $t->string('stage')->default('proposal_sent');
            $t->unsignedInteger('revision')->default(0);
            $t->unsignedBigInteger('amount_cents')->default(0);
            $t->boolean('integrity_blocked')->default(false);
            $t->timestampTz('next_review_at')->nullable();
            $t->string('portal_token_hash')->nullable();
            $t->timestampTz('portal_expires_at')->nullable();
            $t->timestampsTz();
            $t->unique(['data_set_id', 'external_id']);
            $t->unique(['portal_token_hash']);
            $t->index(['data_set_id', 'status']);
        });
        Schema::create('import_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('data_set_id')->constrained('data_sets')->cascadeOnDelete();
            $t->string('name');
            $t->string('profile');
            $t->string('sha256');
            $t->string('path')->nullable();
            $t->unsignedInteger('accepted')->default(0);
            $t->unsignedInteger('duplicates')->default(0);
            $t->jsonb('errors');
            $t->timestampsTz();
        });
        Schema::create('case_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('data_set_id')->constrained('data_sets')->cascadeOnDelete();
            $t->foreignId('commercial_case_id')->constrained('commercial_cases')->cascadeOnDelete();
            $t->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $t->string('event_id');
            $t->string('event_type');
            $t->timestampTz('occurred_at');
            $t->timestampTz('ingested_at');
            $t->unsignedInteger('line_number')->nullable();
            $t->string('profile');
            $t->string('payload_hash');
            $t->jsonb('original');
            $t->jsonb('data');
            $t->unique(['data_set_id', 'event_id']);
            $t->index(['commercial_case_id', 'id']);
        });
        Schema::create('quote_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('commercial_case_id')->constrained('commercial_cases')->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->jsonb('lines');
            $t->unsignedBigInteger('subtotal_cents');
            $t->unsignedBigInteger('tax_cents')->default(0);
            $t->unsignedBigInteger('shipping_cents')->default(0);
            $t->unsignedBigInteger('discount_cents')->default(0);
            $t->unsignedBigInteger('total_cents');
            $t->text('terms')->nullable();
            $t->text('authorization_reference');
            $t->timestampTz('valid_until');
            $t->timestampTz('created_at');
            $t->unique(['commercial_case_id', 'version']);
        });
        Schema::create('evaluations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('commercial_case_id')->constrained('commercial_cases')->cascadeOnDelete();
            $t->string('fingerprint');
            $t->unsignedInteger('case_revision');
            $t->string('status')->default('pending');
            $t->string('provider');
            $t->string('model')->nullable();
            $t->string('prompt_version');
            $t->string('rules_version');
            $t->timestampTz('evaluation_at');
            $t->jsonb('snapshot');
            $t->timestampTz('dispatched_at')->nullable();
            $t->timestampTz('started_at')->nullable();
            $t->timestampTz('finished_at')->nullable();
            $t->string('error_code')->nullable();
            $t->text('error_message')->nullable();
            $t->unsignedInteger('duration_ms')->nullable();
            $t->timestampsTz();
            $t->unique(['fingerprint']);
            $t->index(['status', 'dispatched_at']);
        });
        Schema::create('recommendations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('commercial_case_id')->constrained('commercial_cases')->cascadeOnDelete();
            $t->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $t->unsignedInteger('case_revision');
            $t->string('source');
            $t->string('publication_status');
            $t->jsonb('result');
            $t->jsonb('signals');
            $t->jsonb('validation');
            $t->jsonb('alternatives');
            $t->timestampTz('valid_until');
            $t->timestampTz('created_at');
            $t->unique(['evaluation_id']);
            $t->index(['commercial_case_id', 'id']);
        });
        Schema::create('human_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('recommendation_id')->constrained('recommendations')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('decision');
            $t->text('reason');
            $t->timestampTz('created_at');
            $t->unique(['recommendation_id']);
        });
        Schema::create('recommendation_feedback', function (Blueprint $t) {
            $t->id();
            $t->foreignId('recommendation_id')->constrained('recommendations')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('verdict');
            $t->text('reason');
            $t->timestampTz('created_at');
        });
        Schema::create('ai_usages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $t->string('model');
            $t->string('status');
            $t->unsignedBigInteger('reserved_micromxn');
            $t->unsignedBigInteger('charged_micromxn')->nullable();
            $t->unsignedInteger('input_tokens')->nullable();
            $t->unsignedInteger('output_tokens')->nullable();
            $t->string('request_id')->nullable();
            $t->jsonb('pricing');
            $t->timestampsTz();
            $t->unique(['evaluation_id']);
            $t->index(['created_at']);
        });
        Schema::create('ai_budget_locks', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
        });
        DB::table('ai_budget_locks')->insert(['id' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['ai_budget_locks', 'ai_usages', 'recommendation_feedback', 'human_reviews', 'recommendations', 'evaluations', 'quote_versions', 'case_events', 'import_batches', 'commercial_cases', 'products', 'customers', 'data_sets'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
