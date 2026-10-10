<?php

namespace App\Domain\Cases;

use App\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class CustomerDirectory
{
    /** @return Builder<Customer> */
    public function query(): Builder
    {
        $requests = DB::table('purchase_requests as requests')
            ->join('commercial_cases as cases', 'cases.id', '=', 'requests.commercial_case_id')
            ->select('cases.customer_id')->selectRaw('COUNT(*) as requests_count')->groupBy('cases.customer_id');
        $sales = DB::table('sale_records as sales')
            ->join('commercial_cases as cases', 'cases.id', '=', 'sales.commercial_case_id')
            ->select('cases.customer_id')
            ->selectRaw("SUM(CASE WHEN sales.kind = 'payment' THEN sales.amount_cents ELSE 0 END) as paid_cents")
            ->selectRaw("SUM(CASE WHEN sales.kind = 'formalized' THEN sales.amount_cents ELSE 0 END) as formalized_cents")
            ->groupBy('cases.customer_id');
        $activity = DB::table('activity_events')->select('customer_id')
            ->selectRaw('MAX(occurred_at) as last_activity')->groupBy('customer_id');

        return Customer::query()->select('customers.*')
            ->leftJoinSub($requests, 'request_totals', 'request_totals.customer_id', '=', 'customers.id')
            ->leftJoinSub($sales, 'sale_totals', 'sale_totals.customer_id', '=', 'customers.id')
            ->leftJoinSub($activity, 'activity_totals', 'activity_totals.customer_id', '=', 'customers.id')
            ->selectRaw('COALESCE(request_totals.requests_count, 0) as requests_count, COALESCE(sale_totals.paid_cents, 0) as paid_cents, COALESCE(sale_totals.formalized_cents, 0) as formalized_cents, activity_totals.last_activity');
    }

    /** @param array<string, array<string, mixed>> $profile
     * @return array<string, mixed>
     */
    public function row(Customer $customer, array $profile): array
    {
        $activity = $customer->getAttribute('last_activity');

        return ['id' => $customer->id, 'company' => $customer->company, 'contact_name' => $customer->contact_name,
            'email' => $customer->email, 'commercialProfile' => $profile,
            'requests_count' => (int) $customer->getAttribute('requests_count'),
            'paid_cents' => (int) $customer->getAttribute('paid_cents'),
            'formalized_cents' => (int) $customer->getAttribute('formalized_cents'),
            'last_activity' => $activity ? CarbonImmutable::parse($activity, 'UTC')->toIso8601ZuluString() : null];
    }
}
