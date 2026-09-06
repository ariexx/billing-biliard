<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Basis untuk master data (Product, Hour, Payment).
 *
 * Filament v2 mengembalikan true untuk resource yang tidak punya policy sama
 * sekali, jadi tanpa kelas-kelas ini master data terbuka penuh bagi siapa pun
 * yang berhasil masuk panel.
 */
abstract class AdminOnlyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, $record = null): bool
    {
        return $user->role === 'admin';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, $record = null): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, $record = null): bool
    {
        return $user->role === 'admin';
    }

    public function deleteAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function restore(User $user, $record = null): bool
    {
        return $user->role === 'admin';
    }

    public function restoreAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function forceDelete(User $user, $record = null): bool
    {
        return $user->role === 'admin';
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->role === 'admin';
    }
}
