<?php

namespace App\Console\Commands;

use App\Domain\Demo\DemoIntegrityAudit;
use App\Domain\Demo\DemoScenarioLoader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyDemo extends Command
{
    protected $signature = 'byecode:verify-demo';

    protected $description = 'Comprueba relaciones, importes, evidencia, adjuntos y aislamiento de la demostración.';

    public function handle(DemoIntegrityAudit $audit): int
    {
        $run = DB::table('demo_runs')->where('key', DemoScenarioLoader::KEY)->where('status', 'ready')->firstOrFail();
        $this->line(json_encode($audit->verify($run->id), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
