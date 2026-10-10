<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $status
 * @property string $data_kind
 * @property array<string, mixed> $report
 * @property array<string, mixed>|null $model
 * @property string $dataset_hash
 * @property CarbonImmutable $created_at
 */
class PredictionRun extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['report' => 'array', 'model' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
