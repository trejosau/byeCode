<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string|null $demo_run_id
 * @property int $id
 * @property string|null $public_id
 * @property int|null $assigned_to
 * @property int $data_set_id
 * @property ?int $customer_id
 * @property string $external_id
 * @property string $title
 * @property string $status
 * @property string $stage
 * @property int $revision
 * @property int|null $amount_cents
 * @property bool $integrity_blocked
 * @property ?Carbon $next_review_at
 * @property ?string $portal_token_hash
 * @property ?Carbon $portal_expires_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class CommercialCase extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = [];

    protected $table = 'commercial_cases';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['revision' => 'integer', 'amount_cents' => 'integer', 'integrity_blocked' => 'boolean', 'next_review_at' => 'immutable_datetime', 'portal_expires_at' => 'immutable_datetime'];
    }
}
