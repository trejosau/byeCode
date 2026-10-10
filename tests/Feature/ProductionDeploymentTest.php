<?php

use App\Console\Commands\SetupCommand;
use App\Http\Middleware\TrustRailwayProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

it('passes explicit production authorization to incremental migrations', function (bool $force) {
    config(['app.key' => 'existing-key']);
    $command = Mockery::mock(SetupCommand::class.'[call]');
    $command->shouldReceive('call')->once()->with('migrate', [
        '--no-interaction' => true,
        '--force' => $force,
    ])->andReturn(0);
    $command->setLaravel(app());

    expect($command->run(new ArrayInput($force ? ['--force' => true] : []), new BufferedOutput))->toBe(0);
})->with([true, false]);

it('refuses to generate an encryption key in production', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.key' => null]);

    $this->artisan('byecode:setup', ['--force' => true])
        ->expectsOutput('APP_KEY debe configurarse antes de desplegar. No se generará una clave en producción.')
        ->assertFailed();
});

it('recognizes Railway HTTPS and the client IP without trusting an injected host', function () {
    app()->detectEnvironment(fn () => 'production');
    $request = Request::create('http://byecode-production.up.railway.app/login', server: [
        'REMOTE_ADDR' => '10.0.0.1',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_PORT' => '443',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
        'HTTP_X_FORWARDED_HOST' => 'attacker.example',
    ]);

    try {
        (new TrustRailwayProxies)->handle($request, function (Request $request) {
            expect($request->isSecure())->toBeTrue()
                ->and($request->ip())->toBe('203.0.113.10')
                ->and($request->getHost())->toBe('byecode-production.up.railway.app')
                ->and($request->getPort())->toBe(443);

            return response('ok');
        });
    } finally {
        Request::setTrustedProxies([], 0);
    }
});

it('does not trust forwarded headers during local development', function () {
    app()->detectEnvironment(fn () => 'local');
    $request = Request::create('http://localhost/', server: [
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
    ]);

    try {
        (new TrustRailwayProxies)->handle($request, function (Request $request) {
            expect($request->isSecure())->toBeFalse()
                ->and($request->ip())->toBe('127.0.0.1');

            return response('ok');
        });
    } finally {
        Request::setTrustedProxies([], 0);
    }
});

it('fails the production healthcheck without revealing database credentials', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.debug' => false]);
    DB::shouldReceive('selectOne')->once()->with('select 1')
        ->andThrow(new RuntimeException('password=must-never-be-public'));

    $this->getJson('/up')->assertStatus(500)
        ->assertDontSee('must-never-be-public');
});
