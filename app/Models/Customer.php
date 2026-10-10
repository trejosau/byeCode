<?php

namespace App\Models;

use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $account_id
 * @property int $user_id
 * @property string $company
 * @property ?string $contact_name
 * @property ?string $email
 * @property ?string $notes
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class Customer extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = [];

    protected $table = 'customers';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }
}
