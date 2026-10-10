param([ValidateSet('start', 'stop', 'status')][string]$Action = 'start')

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$bin = Join-Path $env:LOCALAPPDATA 'Programs\ByeCodePostgreSQL17\pgsql\bin'
$data = Join-Path $projectRoot '.local\postgresql\data'
$log = Join-Path $projectRoot '.local\postgresql\server.log'
if (-not (Test-Path (Join-Path $bin 'pg_ctl.exe')) -or -not (Test-Path (Join-Path $data 'PG_VERSION'))) {
    throw 'PostgreSQL local no esta instalado e inicializado en esta computadora. Consulta POSTGRESQL_LOCAL.md.'
}

& (Join-Path $bin 'pg_ctl.exe') status -D $data
$running = $LASTEXITCODE -eq 0
switch ($Action) {
    'start' {
        if (-not $running) {
            & (Join-Path $bin 'pg_ctl.exe') start -D $data -l $log -w -t 30
            if ($LASTEXITCODE -ne 0) { throw 'No se pudo iniciar PostgreSQL local.' }
        }
    }
    'stop' {
        if ($running) {
            & (Join-Path $bin 'pg_ctl.exe') stop -D $data -m fast -w -t 30
            if ($LASTEXITCODE -ne 0) { throw 'No se pudo detener PostgreSQL local.' }
        }
    }
    'status' { if (-not $running) { exit 3 } }
}
exit 0
