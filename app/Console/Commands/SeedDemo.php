<?php

namespace App\Console\Commands;

use App\Domain\Demo\DemoPostgresLoader;
use App\Domain\Demo\DemoScenarioLoader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedDemo extends Command
{
    protected $signature = 'byecode:seed-demo {--fixture=operational-demo-v2 : Versión del historial} {--dry-run : Valida configuración y manifiesto sin escribir}';

    protected $description = 'Carga o verifica la historia comercial de demostración sin sobrescribir avances.';

    public function handle(DemoScenarioLoader $loader): int
    {
        $loader = $loader->forVersion((string) $this->option('fixture'));
        app()->instance(DemoScenarioLoader::class, $loader);
        $this->line($this->option('dry-run') ? 'Validando manifiesto…' : 'Cargando historia de demostración; no se envían correos ni se consulta IA…');
        $runner = DB::getDriverName() === 'pgsql' ? app(DemoPostgresLoader::class) : $loader;
        $report = $this->option('dry-run') ? $loader->preflight() : $runner->load(fn (string $message) => $this->line($message));
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
