<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $data_set_id
 * @property int $commercial_case_id
 * @property ?int $import_batch_id
 * @property string $event_id
 * @property string $event_type
 * @property Carbon $occurred_at
 * @property Carbon $ingested_at
 * @property ?int $line_number
 * @property string $profile
 * @property string $payload_hash
 * @property array<array-key, mixed> $original
 * @property array<array-key, mixed> $data
 */
class CaseEvent extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = [];

    protected $table = 'case_events';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime', 'ingested_at' => 'immutable_datetime', 'line_number' => 'integer', 'original' => 'array', 'data' => 'array'];
    }
}
