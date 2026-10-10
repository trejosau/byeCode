<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('customer')->index();
            $t->boolean('must_change_password')->default(false);
            $t->timestampTz('temporary_password_expires_at')->nullable();
        });
        DB::table('users')->update(['role' => 'commerce']);
        Schema::table('customers', fn (Blueprint $t) => $t->foreignId('account_id')->nullable()->unique()->constrained('users')->restrictOnDelete());
        Schema::table('commercial_cases', function (Blueprint $t) {
            $t->unsignedBigInteger('amount_cents')->nullable()->default(null)->change();
            $t->foreignId('assigned_to')->nullable()->index()->constrained('users')->nullOnDelete();
            $t->uuid('public_id')->nullable()->unique();
        });
        foreach (['data_sets', 'customers', 'products'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['user_id']);
                $t->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            });
        }
        Schema::create('product_styles', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('category');
            $t->jsonb('details');
            $t->boolean('published')->default(false)->index();
            $t->timestampsTz();
        });
        Schema::table('products', function (Blueprint $t) {
            $t->foreignId('style_id')->nullable()->index()->constrained('product_styles')->restrictOnDelete();
            $t->jsonb('details')->nullable();
            $t->boolean('published')->default(false);
        });
        Schema::table('quote_versions', fn (Blueprint $t) => $t->timestampTz('withdrawn_at')->nullable());
        Schema::create('purchase_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->index()->constrained('users')->restrictOnDelete();
            $t->foreignId('commercial_case_id')->unique()->constrained()->cascadeOnDelete();
            $t->uuid('submission_id')->unique();
            $t->jsonb('lines');
            $t->text('message')->nullable();
            $t->date('desired_date')->nullable();
            $t->string('source');
            $t->timestampsTz();
        });
        Schema::create('quote_drafts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('commercial_case_id')->unique()->constrained()->cascadeOnDelete();
            $t->jsonb('payload');
            $t->unsignedInteger('revision')->default(1);
            $t->timestampsTz();
        });
        Schema::create('activity_events', function (Blueprint $t) {
            $t->id();
            $t->uuid('event_id')->unique();
            $t->foreignId('account_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $t->foreignId('customer_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $t->foreignId('commercial_case_id')->nullable()->index()->constrained()->cascadeOnDelete();
            $t->string('session_key')->nullable()->index();
            $t->string('type')->index();
            $t->string('actor');
            $t->string('source');
            $t->unsignedInteger('schema_version')->default(1);
            $t->jsonb('data');
            $t->timestampTz('occurred_at');
            $t->timestampTz('ingested_at');
            $t->index(['customer_id', 'id']);
        });
        Schema::create('chat_messages', function (Blueprint $t) {
            $t->id();
            $t->uuid('event_id')->unique();
            $t->foreignId('commercial_case_id')->index()->constrained()->cascadeOnDelete();
            $t->foreignId('author_id')->index()->constrained('users')->restrictOnDelete();
            $t->string('author_role');
            $t->text('body');
            $t->unsignedInteger('quote_version')->nullable();
            $t->string('attachment_path')->nullable();
            $t->string('attachment_name')->nullable();
            $t->timestampTz('created_at');
        });
        Schema::create('buyer_decisions', function (Blueprint $t) {
            $t->id();
            $t->uuid('event_id')->unique();
            $t->foreignId('commercial_case_id')->index()->constrained()->cascadeOnDelete();
            $t->foreignId('account_id')->index()->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('quote_version');
            $t->string('kind');
            $t->jsonb('lines');
            $t->text('conditions')->nullable();
            $t->timestampTz('created_at');
        });
        Schema::create('sale_records', function (Blueprint $t) {
            $t->id();
            $t->uuid('event_id')->unique();
            $t->foreignId('commercial_case_id')->index()->constrained()->cascadeOnDelete();
            $t->foreignId('author_id')->index()->constrained('users')->restrictOnDelete();
            $t->string('kind');
            $t->unsignedBigInteger('amount_cents')->nullable();
            $t->text('reference');
            $t->timestampTz('occurred_at');
            $t->timestampTz('created_at');
        });
        Schema::create('profile_facts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->index()->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('scope');
            $t->jsonb('value');
            $t->string('nature');
            $t->string('confidence')->nullable();
            $t->jsonb('evidence');
            $t->string('status')->default('active');
            $t->string('model')->nullable();
            $t->string('processor_version');
            $t->unsignedBigInteger('supersedes_id')->nullable();
            $t->timestampTz('observed_at');
            $t->timestampTz('valid_until')->nullable();
            $t->timestampTz('created_at');
            $t->index(['customer_id', 'status']);
        });
        Schema::create('profile_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->index()->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('cutoff');
            $t->string('status');
            $t->string('error')->nullable();
            $t->jsonb('context');
            $t->timestampsTz();
            $t->unique(['customer_id', 'cutoff']);
        });
        Schema::table('ai_usages', fn (Blueprint $t) => $t->foreignId('profile_run_id')->nullable()->unique()->constrained()->nullOnDelete());
        Schema::create('mail_deliveries', function (Blueprint $t) {
            $t->id();
            $t->string('dedup_key')->unique();
            $t->string('recipient');
            $t->string('subject');
            $t->text('encrypted_body')->nullable();
            $t->string('status')->default('pending')->index();
            $t->unsignedInteger('attempts')->default(0);
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Esta migración conserva historial. Use una migración correctiva.');
    }
};
