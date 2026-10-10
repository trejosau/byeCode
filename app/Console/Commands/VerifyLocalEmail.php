<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class VerifyLocalEmail extends Command
{
    protected $signature = 'byecode:verify-local-email {email : Correo de tu cuenta local}';

    protected $description = 'Confirma una cuenta en desarrollo local, donde los correos se guardan en el log';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Solo disponible en APP_ENV=local.');

            return self::FAILURE;
        }
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Primero registra la cuenta en la aplicación.');

            return self::FAILURE;
        }
        $user->markEmailAsVerified();
        $this->info('Cuenta local confirmada. Ya puedes abrir el panel.');

        return self::SUCCESS;
    }
}
