<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class InitialAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = mb_strtolower((string) config('onboarding.admin_email'));
        $existing = User::where('email', $email)->first();
        if ($existing) {
            if ($existing->role !== 'admin') {
                throw new \RuntimeException('El correo inicial ya pertenece a otro perfil.');
            }

            return;
        }
        $data = ['name' => config('onboarding.admin_name'), 'email' => $email, 'password' => config('onboarding.admin_password')];
        $passwordRule = app()->environment(['local', 'testing'])
            ? Password::min(8)
            : Password::min(12)->letters()->numbers();
        Validator::make($data, ['name' => 'required|string|max:255', 'email' => 'required|email', 'password' => ['required', $passwordRule]])->validate();
        $user = new User($data);
        $user->forceFill(['role' => 'admin', 'email_verified_at' => now(), 'must_change_password' => false])->save();
    }
}
