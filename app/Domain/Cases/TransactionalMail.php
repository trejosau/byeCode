<?php

namespace App\Domain\Cases;

use App\Domain\Demo\DemoPolicy;
use App\Jobs\DeliverTransactionalMail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class TransactionalMail
{
    public function queue(string $key, string $recipient, string $subject, string $body, ?\DateTimeInterface $expiresAt = null): void
    {
        $demoRun = DemoPolicy::recipientRun($recipient);
        DB::table('mail_deliveries')->insertOrIgnore(['demo_run_id' => $demoRun, 'dedup_key' => $key, 'recipient' => $recipient, 'subject' => $subject,
            'encrypted_body' => $demoRun ? null : Crypt::encryptString($body), 'expires_at' => $expiresAt, 'status' => $demoRun ? 'suppressed_demo' : 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $id = (int) DB::table('mail_deliveries')->where('dedup_key', $key)->value('id');
        if (! $demoRun && config('onboarding.mail_enabled')) {
            DeliverTransactionalMail::dispatch($id)->afterCommit();
        }
    }
}
