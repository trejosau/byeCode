<?php

namespace App\Console\Commands;

use App\Domain\Cases\CatalogUpgrade;
use Illuminate\Console\Command;

class UpgradeCatalog extends Command
{
    protected $signature = 'byecode:upgrade-catalog {--dry-run}';

    protected $description = 'Actualiza catálogo v1 conservando IDs, historial e importes';

    public function handle(CatalogUpgrade $upgrade): int
    {
        $report = $upgrade->run((bool) $this->option('dry-run'));
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return empty($report['conflicts']) ? self::SUCCESS : self::FAILURE;
    }
}
