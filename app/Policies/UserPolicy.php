<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Only admins manage accounts, and nobody can delete or demote their own account.
 */
class UserPolicy extends AdminOnlyPolicy
{
    public function delete(User $user, Model $model): bool
    {
        return $user->isAdmin() && ! $user->is($model);
    }

    public function changeRole(User $user, User $model): bool
    {
        return $user->isAdmin() && ! $user->is($model);
    }
}
