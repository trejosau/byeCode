<?php

namespace App\Domain\Cases;

use App\Models\CommercialCase;
use App\Models\DataSet;
use App\Models\User;

final class Workspace
{
    public function live(User $user): DataSet
    {
        return DataSet::firstOrCreate(['user_id' => $user->id, 'mode' => 'live'], ['name' => 'Operación comercial']);
    }

    public function ownCase(User $user, CommercialCase $case): DataSet
    {
        abort_unless(in_array($user->role, ['commerce', 'admin'], true), 403);

        return DataSet::whereKey($case->data_set_id)->where(fn ($q) => $q->where('mode', 'live')->orWhere('user_id', $user->id))->firstOrFail();
    }

    public function ownSet(User $user, int $id): DataSet
    {
        return DataSet::whereKey($id)->where('user_id', $user->id)->firstOrFail();
    }
}
