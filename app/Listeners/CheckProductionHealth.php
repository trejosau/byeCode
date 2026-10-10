<?php

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class CheckProductionHealth
{
    public function handle(DiagnosingHealth $event): void
    {
        if (! app()->isProduction()) {
            return;
        }

        try {
            DB::selectOne('select 1');

            if (! is_writable(storage_path('app/private')) || ! is_writable(storage_path('app/public'))) {
                throw new RuntimeException('Storage unavailable');
            }
        } catch (Throwable) {
            // The public health endpoint must never reveal connection details.
            throw new RuntimeException('Production dependencies unavailable.');
        }
    }
}
