<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CommerceTeamSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach ([
                'jorge@gmail.com' => 'Jorge',
                'dani@gmail.com' => 'Dani',
                'jesus@gmail.com' => 'Jesús',
                'sau@gmail.com' => 'Sau',
            ] as $email => $name) {
                $existing = User::where('email', $email)->first();
                if ($existing) {
                    if ($existing->role !== 'commerce') {
                        throw new \RuntimeException("El correo {$email} ya pertenece a otro perfil.");
                    }

                    continue;
                }

                $data = Validator::make([
                    'name' => $name,
                    'email' => $email,
                    'password' => config('onboarding.commerce_team_password'),
                ], [
                    'name' => 'required|string|max:255',
                    'email' => 'required|email',
                    'password' => 'required|string|min:8',
                ])->validate();

                $user = new User($data);
                $user->forceFill([
                    'role' => 'commerce',
                    'email_verified_at' => now(),
                    'must_change_password' => false,
                ])->save();
            }
        });
    }
}
