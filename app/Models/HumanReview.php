<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $recommendation_id
 * @property int $user_id
 * @property string $decision
 * @property string $reason
 * @property Carbon $created_at
 */
class HumanReview extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = [];

    protected $table = 'human_reviews';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
