<?php

namespace App\Jobs;

use App\Domain\Cases\CustomerProfile;
use App\Models\Customer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnrichCustomer implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 120;

    public function __construct(public int $customerId, public bool $retryFailures = false) {}

    public function uniqueId(): string
    {
        return (string) $this->customerId;
    }

    public function handle(CustomerProfile $profiles): void
    {
        if ($customer = Customer::find($this->customerId)) {
            $profiles->process($customer, $this->retryFailures);
        }
    }
}
