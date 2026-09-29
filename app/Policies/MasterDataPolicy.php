<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Master data (places, hotels, vehicles, …): agents may look, only admins may change
 * (SRS 3.1: agents cannot manage master data).
 */
abstract class MasterDataPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, Model $model): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Model $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->isAdmin();
    }
}
