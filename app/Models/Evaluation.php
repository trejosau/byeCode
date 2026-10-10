<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $commercial_case_id
 * @property string $fingerprint
 * @property int $case_revision
 * @property string $status
 * @property string $provider
 * @property ?string $model
 * @property string $prompt_version
 * @property string $rules_version
 * @property Carbon $evaluation_at
 * @property array<array-key, mixed> $snapshot
 * @property ?Carbon $dispatched_at
 * @property ?Carbon $started_at
 * @property ?Carbon $finished_at
 * @property ?string $error_code
 * @property ?string $error_message
 * @property ?int $duration_ms
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class Evaluation extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = [];

    protected $table = 'evaluations';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['case_revision' => 'integer', 'evaluation_at' => 'immutable_datetime', 'snapshot' => 'array', 'dispatched_at' => 'immutable_datetime', 'started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime', 'duration_ms' => 'integer'];
    }
}
