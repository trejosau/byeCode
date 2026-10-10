<?php

use App\Jobs\DeliverTransactionalMail;
use App\Jobs\EnrichCustomer;
use App\Models\Customer;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::command('byecode:dispatch')->everyMinute()->withoutOverlapping();
Schedule::call(function () {
    // A killed worker must leave an explicit, reviewable outcome. Never blindly resend.
    DB::table('mail_deliveries')->where('status', 'pending')->whereNotNull('expires_at')->where('expires_at', '<=', now())
        ->update(['status' => 'expired', 'encrypted_body' => null, 'updated_at' => now()]);
    DB::table('mail_deliveries')->where('status', 'sending')->where('updated_at', '<', now()->subMinutes(10))
        ->update(['status' => 'uncertain', 'encrypted_body' => null, 'updated_at' => now()]);
    $interrupted = DB::table('profile_runs')->where('status', 'running')->where('updated_at', '<', now()->subMinutes(10))->pluck('id');
    DB::table('ai_usages')->whereIn('profile_run_id', $interrupted)->where('status', 'reserved')->update(['status' => 'uncertain']);
    DB::table('profile_runs')->whereIn('id', $interrupted)->update(['status' => 'failed', 'error' => 'worker_interrupted', 'updated_at' => now()]);
    if (config('integrations.openai.api_key')) {
        Customer::whereNotNull('account_id')->whereNull('demo_run_id')->chunkById(100, function ($customers) {
            foreach ($customers as $customer) {
                EnrichCustomer::dispatch($customer->id);
            }
        });
    }
    if (config('onboarding.mail_enabled')) {
        DB::table('mail_deliveries')->where('status', 'pending')->orderBy('id')->limit(30)->get(['id'])->each(fn ($row) => DeliverTransactionalMail::dispatch($row->id));
    }
})->everyFiveMinutes()->name('customer-profiles-and-mail')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
