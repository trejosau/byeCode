<?php

namespace App\Domain\Cases;

use App\Domain\Demo\DemoPolicy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Onboarding
{
    public function create(string $name, string $email, string $role): User
    {
        return DB::transaction(function () use ($name, $email, $role) {
            $email = mb_strtolower(trim($email));
            $existing = User::where('email', $email)->lockForUpdate()->first();
            if ($existing) {
                if ($role !== 'customer' || $existing->role !== 'customer') {
                    throw ValidationException::withMessages(['email' => 'El correo ya corresponde a una cuenta existente.']);
                }

                return $existing;
            }
            $password = Str::password(24);
            $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
            $user->forceFill(['demo_run_id' => $role === 'customer' ? DemoPolicy::activeRunId() : null, 'role' => $role, 'must_change_password' => true, 'temporary_password_expires_at' => now()->addDay()])->save();
            app(TransactionalMail::class)->queue('welcome:'.$user->id, $email, 'Tu acceso a ByeCode',
                "Hola {$name}.\nTu cuenta está lista.\nCorreo: {$email}\nContraseña temporal: {$password}\nCaduca en 24 horas. Deberás cambiarla al entrar.\n".url('/login'), $user->temporary_password_expires_at);

            return $user;
        });
    }
}
