<?php

use App\Models\User;
use Database\Seeders\CommerceTeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['onboarding.commerce_team_password' => '87654321']);
});

it('creates the four commerce accounts with hashed credentials and direct workspace access', function () {
    $this->seed(CommerceTeamSeeder::class);

    expect(User::count())->toBe(4);
    foreach (['jorge@gmail.com', 'dani@gmail.com', 'jesus@gmail.com', 'sau@gmail.com'] as $email) {
        $user = User::where('email', $email)->firstOrFail();
        expect($user->role)->toBe('commerce')
            ->and($user->hasVerifiedEmail())->toBeTrue()
            ->and(Hash::check('87654321', $user->password))->toBeTrue()
            ->and($user->password)->not->toBe('87654321')
            ->and($user->must_change_password)->toBeFalse();

        $this->post('/login', ['email' => $email, 'password' => '87654321'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
        $this->get('/workspace')->assertOk();
        $this->post('/logout');
    }
});

it('preserves the administrator and changed commerce credentials when repeated without a password', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $adminAttributes = $admin->refresh()->getRawOriginal();
    $this->seed(CommerceTeamSeeder::class);
    $jorge = User::where('email', 'jorge@gmail.com')->firstOrFail();
    $jorge->forceFill(['name' => 'Jorge actualizado', 'password' => 'ChangedPassword123!', 'must_change_password' => false])->save();
    $jorgeAttributes = $jorge->refresh()->getRawOriginal();

    config(['onboarding.commerce_team_password' => null]);
    $this->seed(CommerceTeamSeeder::class);

    expect(User::count())->toBe(5)
        ->and($admin->fresh()->getRawOriginal())->toBe($adminAttributes)
        ->and($jorge->fresh()->getRawOriginal())->toBe($jorgeAttributes);
});

it('rolls back the entire batch when an email belongs to another role', function (string $role) {
    $existing = User::factory()->create(['email' => 'sau@gmail.com', 'role' => $role]);
    $attributes = $existing->refresh()->getRawOriginal();

    expect(fn () => $this->seed(CommerceTeamSeeder::class))->toThrow(RuntimeException::class);

    expect(User::count())->toBe(1)
        ->and($existing->fresh()->getRawOriginal())->toBe($attributes);
})->with(['admin', 'customer']);

it('requires a configured initial password without creating partial accounts', function () {
    config(['onboarding.commerce_team_password' => null]);

    expect(fn () => $this->seed(CommerceTeamSeeder::class))->toThrow(ValidationException::class);
    expect(User::count())->toBe(0);
});
