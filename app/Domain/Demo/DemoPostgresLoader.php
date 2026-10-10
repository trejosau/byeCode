<?php

namespace App\Domain\Demo;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Replay through the domain locally, then transfer validated rows with reserved identities. */
final class DemoPostgresLoader
{
    /** Dependency order and generous bounds, checked before any destination insert.
     * @var array<string, int>
     */
    private const TABLES = ['users' => 20, 'data_sets' => 4, 'customers' => 20, 'product_styles' => 10, 'products' => 50,
        'commercial_cases' => 50, 'import_batches' => 500, 'case_events' => 500, 'purchase_requests' => 50,
        'quote_versions' => 100, 'quote_drafts' => 20, 'chat_messages' => 200, 'buyer_decisions' => 50,
        'sale_records' => 100, 'activity_events' => 1000, 'profile_facts' => 150, 'evaluations' => 150,
        'recommendations' => 150, 'human_reviews' => 20, 'recommendation_feedback' => 20,
        'profile_runs' => 30, 'mail_deliveries' => 100, 'demo_records' => 3000];

    /** @return array<string, mixed> */
    public function load(?\Closure $progress = null): array
    {
        DemoPolicy::requireLoadingEnabled();
        $lock = Cache::lock('byecode:operational-demo-publish', 3600);
        if (! $lock->get()) {
            throw new RuntimeException('Otra carga está en curso.');
        }
        $stageDir = storage_path('app/private/demo-staging/'.Str::uuid());
        $copied = [];
        $runId = null;
        $committed = false;
        try {
            $loader = app(DemoScenarioLoader::class);
            $preflight = $loader->preflight();
            if ($preflight['installed']) {
                return app(DemoIntegrityAudit::class)->verify($preflight['run_id']);
            }
            $existing = DB::table('demo_runs')->where('key', DemoScenarioLoader::KEY)->first();
            $runId = $existing->id ?? (string) Str::uuid();
            $run = ['id' => $runId, 'key' => DemoScenarioLoader::KEY, 'version' => '1.0.0', 'seed' => 20261010,
                'reference_at' => $existing->reference_at ?? CarbonImmutable::now('UTC')->startOfMinute()->toDateTimeString(),
                'loaded_at' => now()->toDateTimeString(), 'manifest_hash' => $loader->hash(), 'status' => 'loading', 'semantic_policy' => 'rules_only',
                'created_at' => $existing->created_at ?? now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()];
            DB::table('demo_runs')->updateOrInsert(['id' => $runId], $run);
            $progress?->__invoke('Reservando identidades de PostgreSQL para conservar todas las referencias…');
            $ranges = $this->reserveIdentities();
            $actors = array_values(DB::table('users')->where('role', 'commerce')->get(['id', 'name', 'email', 'password', 'role', 'email_verified_at', 'must_change_password', 'created_at', 'updated_at'])->map(fn ($row) => get_object_vars($row))->all());
            $sets = array_values(DB::table('data_sets')->whereIn('user_id', array_column($actors, 'id'))->where('mode', 'live')->get()->map(fn ($row) => get_object_vars($row))->all());
            $prepared = $this->prepare($stageDir, [...$run, 'status' => 'failed'], $ranges, $actors, $sets, $progress);
            $progress?->__invoke('Historia validada. Guardando los registros en Supabase por lotes…');
            DB::transaction(function () use ($prepared, $run, $actors, $sets, &$copied, $stageDir) {
                DB::table('demo_runs')->where('id', $run['id'])->lockForUpdate()->firstOrFail();
                $current = DB::table('users')->whereIn('id', array_column($actors, 'id'))->lockForUpdate()->get()->keyBy('id');
                foreach ($actors as $actor) {
                    if (! isset($current[$actor['id']]) || $current[$actor['id']]->role !== 'commerce' || $current[$actor['id']]->email !== $actor['email']) {
                        throw new RuntimeException('Cambió el equipo durante la preparación; no se confirmó la carga.');
                    }
                }
                foreach ($sets as $set) {
                    $currentSet = DB::table('data_sets')->where('id', $set['id'])->lockForUpdate()->firstOrFail();
                    if ($currentSet->user_id !== $set['user_id'] || $currentSet->mode !== 'live' || $currentSet->integrity_blocked) {
                        throw new RuntimeException('Cambió el espacio operativo durante la preparación.');
                    }
                }
                foreach ($prepared['files'] as $path) {
                    if (Storage::disk('local')->exists($path)) {
                        throw new RuntimeException('Un archivo privado de destino ya existe.');
                    }
                    $stream = fopen($stageDir.'/files/'.$path, 'rb');
                    if ($stream === false) {
                        throw new RuntimeException('No se pudo leer un archivo preparado.');
                    }
                    try {
                        $copied[] = $path;
                        if (! Storage::disk('local')->put($path, $stream)) {
                            throw new RuntimeException('No se pudo guardar un archivo privado.');
                        }
                    } finally {
                        fclose($stream);
                    }
                }
                foreach ($prepared['rows'] as $table => $rows) {
                    foreach (array_chunk($rows, 100) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                }
                $report = app(DemoIntegrityAudit::class)->verify($run['id'], true);
                DB::table('demo_runs')->where('id', $run['id'])->update(['status' => 'ready', 'report' => json_encode($report, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            });
            $committed = true;

            return app(DemoIntegrityAudit::class)->verify($runId);
        } catch (\Throwable $e) {
            if (! $committed) {
                Storage::disk('local')->delete($copied);
                if ($runId) {
                    DB::table('demo_runs')->where('id', $runId)->update(['status' => 'failed', 'updated_at' => now()]);
                }
            }
            throw $e;
        } finally {
            $base = realpath(storage_path('app/private/demo-staging'));
            $target = realpath($stageDir);
            if ($base && $target && str_starts_with($target, $base.DIRECTORY_SEPARATOR)) {
                File::deleteDirectory($target);
            }
            $lock->release();
        }
    }

    /** Sequence reservations never rewind or reuse IDs; concurrent inserts cannot enter a reserved range.
     * @return array<string, array{first: int, last: int}>
     */
    private function reserveIdentities(): array
    {
        $ranges = [];
        foreach (self::TABLES as $table => $size) {
            if (DB::getDriverName() === 'pgsql') {
                for ($attempt = 0; $attempt < 3; $attempt++) {
                    $allocation = DB::selectOne("WITH reserved AS MATERIALIZED (SELECT nextval(pg_get_serial_sequence(?, 'id')) AS id FROM generate_series(1, ?)) SELECT min(id) AS first, max(id) AS last FROM reserved", [$table, $size]);
                    if ((int) $allocation->last - (int) $allocation->first + 1 === $size) {
                        break;
                    }
                }
                if ((int) $allocation->last - (int) $allocation->first + 1 !== $size) {
                    throw new RuntimeException('No se pudo reservar un rango exclusivo para '.$table);
                }
                $ranges[$table] = ['first' => (int) $allocation->first, 'last' => (int) $allocation->last];
            } elseif (app()->environment('testing')) {
                $first = (int) DB::table($table)->max('id') + 1;
                $ranges[$table] = ['first' => $first, 'last' => $first + $size - 1];
            } else {
                throw new RuntimeException('La transferencia preparada requiere PostgreSQL.');
            }
        }

        return $ranges;
    }

    /** @param array<string, mixed> $run
     * @param  array<string, array{first: int, last: int}>  $ranges
     * @param  list<array<string, mixed>>  $actors
     * @param  list<array<string, mixed>>  $sets
     * @return array{rows: array<string, list<array<string, mixed>>>, files: list<string>}
     */
    private function prepare(string $directory, array $run, array $ranges, array $actors, array $sets, ?\Closure $progress): array
    {
        File::ensureDirectoryExists($directory.'/files');
        touch($directory.'/replay.sqlite');
        $connection = DB::getDefaultConnection();
        $cache = config('cache.default');
        $local = config('filesystems.disks.local');
        $originalDisk = Storage::disk('local');
        config(['database.connections.demo_stage' => ['driver' => 'sqlite', 'database' => $directory.'/replay.sqlite', 'prefix' => '', 'foreign_key_constraints' => true],
            'cache.default' => 'array', 'filesystems.disks.local.root' => $directory.'/files']);
        DB::setDefaultConnection('demo_stage');
        Schema::clearResolvedInstance('db.schema');
        app()->forgetInstance('db.schema');
        Storage::forgetDisk('local');
        try {
            Artisan::call('migrate', ['--database' => 'demo_stage', '--force' => true]);
            DB::table('users')->insert($actors);
            if ($sets) {
                DB::table('data_sets')->insert($sets);
            }
            DB::table('demo_runs')->insert($run);
            foreach ($ranges as $table => $range) {
                DB::table('sqlite_sequence')->updateOrInsert(['name' => $table], ['seq' => $range['first'] - 1]);
            }
            app(DemoScenarioLoader::class)->load($progress);
            $rows = [];
            foreach ($ranges as $table => $range) {
                $rows[$table] = array_values(DB::table($table)->where('id', '>=', $range['first'])->orderBy('id')->get()->map(fn ($row) => get_object_vars($row))->all());
                if (array_any($rows[$table], fn ($row) => $row['id'] > $range['last'])) {
                    throw new RuntimeException('El manifiesto superó la reserva de identidades de '.$table);
                }
            }

            return ['rows' => $rows, 'files' => array_values(Storage::disk('local')->allFiles())];
        } finally {
            DB::purge('demo_stage');
            DB::setDefaultConnection($connection);
            app('migration.repository')->setSource($connection);
            Schema::clearResolvedInstance('db.schema');
            app()->forgetInstance('db.schema');
            config(['cache.default' => $cache, 'filesystems.disks.local' => $local]);
            Storage::set('local', $originalDisk);
        }
    }
}
