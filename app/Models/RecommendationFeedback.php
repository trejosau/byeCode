<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $recommendation_id
 * @property int $user_id
 * @property string $verdict
 * @property string $reason
 * @property Carbon $created_at
 */
class RecommendationFeedback extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = [];

    protected $table = 'recommendation_feedback';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
