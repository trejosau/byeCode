<?php

namespace App\Jobs;

use App\Domain\Cases\CustomerProfile;
use App\Models\Customer;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class EnrichCustomer implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 120;

    public int $timeout = 55;

    public function __construct(public int $customerId, public bool $retryFailures = false) {}

    public function uniqueId(): string
    {
        return (string) $this->customerId;
    }

    public function handle(CustomerProfile $profiles): void
    {
        if ($customer = Customer::find($this->customerId)) {
            $before = DB::table('profile_runs')->where('customer_id', $customer->id)->where('status', 'complete')->max('cutoff') ?? 0;
            $profiles->process($customer, $this->retryFailures);
            $after = DB::table('profile_runs')->where('customer_id', $customer->id)->where('status', 'complete')->max('cutoff') ?? 0;
            if ($after > $before && DB::table('activity_events')->where('customer_id', $customer->id)->where('actor', '!=', 'ai')->where('id', '>', $after)->exists()) {
                self::dispatch($customer->id);
            }
        }
    }
}
