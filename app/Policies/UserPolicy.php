<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, ?User $record = null): bool
    {
        return $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, ?User $record = null): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Akun sendiri tidak boleh dihapus supaya panel tidak terkunci tanpa admin.
     */
    public function delete(User $user, ?User $record = null): bool
    {
        return $user->role === 'admin'
            && (string) $record?->uuid !== (string) $user->uuid;
    }

    public function deleteAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function restore(User $user, ?User $record = null): bool
    {
        return $user->role === 'admin';
    }

    public function restoreAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function forceDelete(User $user, ?User $record = null): bool
    {
        return $user->role === 'admin';
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->role === 'admin';
    }
}
