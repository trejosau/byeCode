<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $commercial_case_id
 * @property int $evaluation_id
 * @property int $case_revision
 * @property string $source
 * @property string $publication_status
 * @property array<array-key, mixed> $result
 * @property list<array<string, mixed>> $signals
 * @property array<array-key, mixed> $validation
 * @property array<array-key, mixed> $alternatives
 * @property Carbon $valid_until
 * @property Carbon $created_at
 */
class Recommendation extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = [];

    protected $table = 'recommendations';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['case_revision' => 'integer', 'result' => 'array', 'signals' => 'array', 'validation' => 'array', 'alternatives' => 'array', 'valid_until' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }
}
