<?php

namespace App\Console\Commands;

use App\Domain\Cases\CatalogManifest;
use Illuminate\Console\Command;

class VerifyCatalog extends Command
{
    protected $signature = 'byecode:verify-catalog {--data-only : Valida atributos y rutas previstas sin exigir las imágenes}';

    protected $description = 'Verifica las 36 variantes y la cobertura de los 48 recursos del catálogo';

    public function handle(CatalogManifest $manifest): int
    {
        $errors = $manifest->imageErrors(! $this->option('data-only'));
        foreach ($errors as $error) {
            $this->error($error);
        }
        if ($errors) {
            return self::FAILURE;
        }
        $this->info($this->option('data-only') ? 'Catálogo válido: 6 modelos, 36 variantes y 48 rutas previstas. Imágenes sin verificar.' : 'Catálogo e imágenes válidos: 6 modelos, 36 variantes y 48 PNG con canal alfa.');

        return self::SUCCESS;
    }
}
