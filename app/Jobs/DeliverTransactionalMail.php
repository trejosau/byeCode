<?php

namespace App\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class DeliverTransactionalMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        if (! config('onboarding.mail_enabled') || config('mail.default') !== 'smtp') {
            return;
        }
        if (! DB::table('mail_deliveries')->where('id', $this->deliveryId)->where('status', 'pending')->update(['status' => 'sending', 'updated_at' => now()])) {
            return;
        }
        $row = DB::table('mail_deliveries')->where('id', $this->deliveryId)->firstOrFail();
        if ($row->expires_at && CarbonImmutable::parse($row->expires_at)->isPast()) {
            DB::table('mail_deliveries')->where('id', $row->id)->update(['status' => 'expired', 'encrypted_body' => null, 'updated_at' => now()]);

            return;
        }
        DB::table('mail_deliveries')->where('id', $this->deliveryId)->increment('attempts');
        try {
            Mail::raw(Crypt::decryptString($row->encrypted_body), function ($mail) use ($row) {
                $mail->to($row->recipient)->subject($row->subject);
            });
            DB::table('mail_deliveries')->where('id', $row->id)->update(['status' => 'smtp_accepted', 'encrypted_body' => null, 'updated_at' => now()]);
        } catch (\Throwable) {
            // SMTP timeouts can occur after acceptance. Never retry credentials blindly.
            DB::table('mail_deliveries')->where('id', $row->id)->update(['status' => 'uncertain', 'encrypted_body' => null, 'updated_at' => now()]);
        }
    }
}
