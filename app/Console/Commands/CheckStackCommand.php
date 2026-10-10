<?php

namespace App\Console\Commands;

use App\Jobs\VerifyQueueConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CheckStackCommand extends Command
{
    protected $signature = 'byecode:check {--queue : Procesa un job diagnóstico en una cola exclusiva}';

    protected $description = 'Comprueba PostgreSQL, migraciones, caché y el worker sin mostrar credenciales';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->error('La aplicación debe usar PostgreSQL.');

            return self::FAILURE;
        }

        $database = DB::selectOne('select current_schema() as schema, version() as version');
        $this->info('PostgreSQL conectado. Esquema: '.$database->schema);

        foreach (['users', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error('Falta la tabla: '.$table);

                return self::FAILURE;
            }
        }

        $key = 'byecode-stack-check-'.Str::uuid();

        try {
            Cache::put($key, 'ok', 60);

            if (Cache::pull($key) !== 'ok') {
                $this->error('La caché no devolvió el valor esperado.');

                return self::FAILURE;
            }

            $this->info('Tablas y caché: OK.');

            if ($this->option('queue')) {
                if (config('queue.default') !== 'database') {
                    $this->error('La comprobación requiere QUEUE_CONNECTION=database.');

                    return self::FAILURE;
                }

                VerifyQueueConnection::dispatch($key)->onQueue($key);

                $worker = Process::path(base_path())->timeout(45)->run([
                    PHP_BINARY, 'artisan', 'queue:work', '--queue='.$key,
                    '--once', '--tries=1', '--timeout=30', '--no-interaction',
                ]);

                if (! $worker->successful() || Cache::get($key) !== 'ok') {
                    $this->error('El worker no completó la comprobación. Revisar el log local.');

                    return self::FAILURE;
                }

                $this->info('Job persistido y procesado por otro proceso: OK.');
            }
        } finally {
            Cache::forget($key);
        }

        return self::SUCCESS;
    }
}
