<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property ?int $evaluation_id
 * @property string $model
 * @property string $status
 * @property int $reserved_micromxn
 * @property ?int $charged_micromxn
 * @property ?int $input_tokens
 * @property ?int $output_tokens
 * @property ?string $request_id
 * @property array<array-key, mixed> $pricing
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class AiUsage extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = [];

    protected $table = 'ai_usages';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['reserved_micromxn' => 'integer', 'charged_micromxn' => 'integer', 'input_tokens' => 'integer', 'output_tokens' => 'integer', 'pricing' => 'array'];
    }
}
