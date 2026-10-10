<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int|null $style_id
 * @property bool $published
 * @property array<string, mixed>|null $details
 * @property int $user_id
 * @property string $sku
 * @property string $name
 * @property string $category
 * @property ?string $fit
 * @property ?int $gsm
 * @property ?string $composition
 * @property ?string $color
 * @property ?string $size
 * @property ?int $unit_price_cents
 * @property ?int $moq
 * @property ?int $stock
 * @property ?Carbon $verified_at
 * @property ?string $source
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class Product extends Model
{
    /** @param Builder<Product> $query */
    public function scopeWhereHasStylePublished(Builder $query): void
    {
        $query->whereIn('style_id', DB::table('product_styles')->where('published', true)->select('id'));
    }

    /**
     * @var list<string>
     */
    protected $guarded = [];

    protected $table = 'products';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['details' => 'array', 'published' => 'boolean', 'gsm' => 'integer', 'unit_price_cents' => 'integer', 'moq' => 'integer', 'stock' => 'integer', 'verified_at' => 'immutable_datetime'];
    }
}
