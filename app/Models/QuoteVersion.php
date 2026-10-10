<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $commercial_case_id
 * @property int $version
 * @property array<array-key, mixed> $lines
 * @property int $subtotal_cents
 * @property int $tax_cents
 * @property int $shipping_cents
 * @property int $discount_cents
 * @property int $total_cents
 * @property ?string $terms
 * @property string $authorization_reference
 * @property Carbon $valid_until
 * @property Carbon $created_at
 */
class QuoteVersion extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = [];

    protected $table = 'quote_versions';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['version' => 'integer', 'lines' => 'array', 'subtotal_cents' => 'integer', 'tax_cents' => 'integer', 'shipping_cents' => 'integer', 'discount_cents' => 'integer', 'total_cents' => 'integer', 'valid_until' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }
}
