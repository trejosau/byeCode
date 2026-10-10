<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetupCommand extends Command
{
    protected $signature = 'byecode:setup';

    protected $description = 'Inicializa la clave, el esquema de PostgreSQL y las migraciones pendientes';

    public function handle(): int
    {
        if (! config('app.key')) {
            if ($this->call('key:generate') !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            $schema = config('database.connections.pgsql.search_path');

            if (! is_string($schema) || ! preg_match('/^[a-z][a-z0-9_]{0,62}$/', $schema)
                || in_array($schema, ['public', 'auth', 'storage', 'extensions', 'information_schema'], true)
                || str_starts_with($schema, 'pg_')) {
                $this->error('DB_SCHEMA debe identificar un esquema propio de la aplicación.');

                return self::FAILURE;
            }

            DB::statement('CREATE SCHEMA IF NOT EXISTS "'.$schema.'"');
            $this->info('Esquema PostgreSQL disponible: '.$schema);
        }

        return $this->call('migrate', ['--no-interaction' => true]);
    }
}
