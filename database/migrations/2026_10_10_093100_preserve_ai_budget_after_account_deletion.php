<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usages', function (Blueprint $table) {
            $table->dropForeign(['evaluation_id']);
            $table->foreignId('evaluation_id')->nullable()->change();
            $table->foreign('evaluation_id')->references('id')->on('evaluations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('ai_usages')->whereNull('evaluation_id')->exists()) {
            throw new RuntimeException('Cannot restore cascade deletion while retained budget entries exist.');
        }
        Schema::table('ai_usages', function (Blueprint $table) {
            $table->dropForeign(['evaluation_id']);
            $table->foreignId('evaluation_id')->nullable(false)->change();
            $table->foreign('evaluation_id')->references('id')->on('evaluations')->cascadeOnDelete();
        });
    }
};
