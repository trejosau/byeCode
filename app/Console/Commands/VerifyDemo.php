<?php

namespace App\Console\Commands;

use App\Domain\Demo\DemoIntegrityAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyDemo extends Command
{
    protected $signature = 'byecode:verify-demo {--fixture= : Versión instalada; por defecto verifica todas}';

    protected $description = 'Comprueba relaciones, importes, evidencia, adjuntos y aislamiento de la demostración.';

    public function handle(DemoIntegrityAudit $audit): int
    {
        $runs = DB::table('demo_runs')->where('status', 'ready')->when($this->option('fixture'), fn ($query, $version) => $query->where('key', $version))->get();
        if ($runs->isEmpty()) {
            $this->error('No hay una ejecución lista para verificar.');

            return self::FAILURE;
        }
        foreach ($runs as $run) {
            $this->line(json_encode($audit->verify($run->id), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }

        return self::SUCCESS;
    }
}
