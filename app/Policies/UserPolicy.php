<?php

namespace App\Policies;

use App\Models\User;
use App\Constants\UsersRoles;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->status, [
            UsersRoles::SUPER_ADMIN,
            UsersRoles::MODERATOR, 
            UsersRoles::EDITOR,
            UsersRoles::USER,
            UsersRoles::SUPER_USER,
            UsersRoles::NEW_USER,
        ]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->status === UsersRoles::MODERATOR || $user->status === UsersRoles::EDITOR || $user->status === UsersRoles::SUPER_ADMIN || $user->status === UsersRoles::EDITOR;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->status === UsersRoles::MODERATOR || $user->status === UsersRoles::EDITOR || $user->status === UsersRoles::SUPER_ADMIN || $user->status === UsersRoles::EDITOR;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->status === UsersRoles::MODERATOR || $user->status === UsersRoles::EDITOR || $user->status === UsersRoles::SUPER_ADMIN || $user->status === UsersRoles::EDITOR;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->status === UsersRoles::MODERATOR || $user->status === UsersRoles::EDITOR || $user->status === UsersRoles::SUPER_ADMIN || $user->status === UsersRoles::EDITOR;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->status === UsersRoles::SUPER_ADMIN;;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->status === UsersRoles::SUPER_ADMIN;;
    }
}
