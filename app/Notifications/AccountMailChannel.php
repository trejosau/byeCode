<?php

namespace App\Notifications;

use App\Domain\Cases\TransactionalMail;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Notification;

class AccountMailChannel
{
    public function send(User $notifiable, Notification $notification): void
    {
        if (! $notification instanceof VerifyEmail && ! $notification instanceof ResetPassword) {
            throw new \LogicException('Unsupported account notification.');
        }
        $message = $notification->toMail($notifiable);
        $verify = $notification instanceof VerifyEmail;
        $minutes = $verify ? (int) config('auth.verification.expire', 60) : (int) config('auth.passwords.users.expire', 60);
        $subject = $verify ? 'Verifica tu correo en ByeCode' : 'Recupera tu acceso a ByeCode';
        $body = "Hola {$notifiable->name}.\n".($verify ? 'Confirma tu correo para enviar solicitudes.' : 'Elige una contraseña nueva para tu cuenta.');
        $body .= "\n{$message->actionUrl}\nEl enlace caduca en {$minutes} minutos. Si no lo solicitaste, puedes ignorar este mensaje.";
        app(TransactionalMail::class)->queue('auth:'.hash('sha256', $message->actionUrl), $notifiable->email, $subject, $body, now()->addMinutes($minutes));
    }
}
