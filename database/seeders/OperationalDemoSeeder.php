<?php

namespace Database\Seeders;

use App\Domain\Demo\DemoPostgresLoader;
use App\Domain\Demo\DemoScenarioLoader;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OperationalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $loader = DB::getDriverName() === 'pgsql' ? app(DemoPostgresLoader::class) : app(DemoScenarioLoader::class);
        $loader->load();
    }
}
