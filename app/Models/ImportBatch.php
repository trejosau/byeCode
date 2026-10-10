<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $data_set_id
 * @property string $name
 * @property string $profile
 * @property string $sha256
 * @property ?string $path
 * @property int $accepted
 * @property int $duplicates
 * @property array<array-key, mixed> $errors
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class ImportBatch extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = [];

    protected $table = 'import_batches';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['accepted' => 'integer', 'duplicates' => 'integer', 'errors' => 'array'];
    }
}
