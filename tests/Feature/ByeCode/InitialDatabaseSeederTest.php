<?php

use App\Domain\Demo\DemoPolicy;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('initializes only the administrator commerce team and catalog and preserves later registrations', function () {
    config([
        'onboarding.admin_email' => 'admin@example.test',
        'onboarding.admin_name' => 'Admin',
        'onboarding.admin_password' => 'SeedTest-83x!',
        'onboarding.commerce_team_password' => 'SeedTest-83x!',
    ]);
    $this->seed(DatabaseSeeder::class);
    expect(User::count())->toBe(5)
        ->and(User::where('role', 'customer')->count())->toBe(0)
        ->and(DB::table('customers')->count())->toBe(0)
        ->and(DB::table('commercial_cases')->count())->toBe(0)
        ->and(DB::table('products')->count())->toBe(36)
        ->and(DB::table('product_styles')->count())->toBe(6);
    $admin = User::where('role', 'admin')->firstOrFail();
    expect(Hash::check('SeedTest-83x!', $admin->password))->toBeTrue();
    $admin->update(['password' => 'ChangedPassword123!']);
    $customer = User::factory()->create(['role' => 'customer']);
    $attributes = $customer->refresh()->getRawOriginal();
    $this->seed(DatabaseSeeder::class);
    expect(User::count())->toBe(6)
        ->and(Hash::check('ChangedPassword123!', $admin->fresh()->password))->toBeTrue()
        ->and($customer->fresh()->getRawOriginal())->toBe($attributes)
        ->and(DB::table('products')->count())->toBe(36);
});

it('rejects demo loading and automatic demo registration outside tests even when enabled', function () {
    config(['demo.enabled' => true, 'demo.active_key' => 'operational-demo-v2']);
    $this->app->instance('env', 'local');
    expect(fn () => DemoPolicy::requireLoadingEnabled())->toThrow(RuntimeException::class)
        ->and(DemoPolicy::activeRunId())->toBeNull();
});
