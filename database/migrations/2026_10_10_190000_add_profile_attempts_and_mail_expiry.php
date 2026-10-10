<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_runs', function (Blueprint $t) {
            $t->dropUnique(['customer_id', 'cutoff']);
            $t->unsignedInteger('attempt')->default(1);
            $t->unique(['customer_id', 'cutoff', 'attempt']);
        });
        Schema::table('mail_deliveries', fn (Blueprint $t) => $t->timestampTz('expires_at')->nullable());
    }

    public function down(): void
    {
        throw new RuntimeException('Use una migración correctiva para conservar el historial.');
    }
};
