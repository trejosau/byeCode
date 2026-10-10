<?php

namespace App\Console\Commands;

use App\Domain\Prediction\BusinessHistory;
use App\Models\User;
use Illuminate\Console\Command;

class TrainBusinessHistory extends Command
{
    protected $signature = 'byecode:train-history {--user= : Comercial que inicia el entrenamiento}';

    protected $description = 'Entrena y valida con instantáneas temporales del historial real del aplicativo, sin llamadas externas';

    public function handle(BusinessHistory $history): int
    {
        $user = User::where('role', 'commerce')->whereKey($this->option('user'))->first();
        if (! $user) {
            $this->error('Selecciona un usuario de comercio.');

            return self::FAILURE;
        }
        $run = $history->train($user);
        $this->info('Evaluación '.$run->id.': '.$run->status);

        return self::SUCCESS;
    }
}
