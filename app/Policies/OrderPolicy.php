<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Admin boleh menyentuh semua order. Kasir hanya order yang dia buat sendiri.
 *
 * Sebelumnya policy ini terdaftar tapi tidak pernah dipanggil dari mana pun,
 * sehingga setiap kasir bisa melihat, mengubah, mencetak, dan menghapus item
 * order milik kasir lain.
 */
class OrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'cashier']);
    }

    public function view(User $user, ?Order $order = null): bool
    {
        return $this->owns($user, $order);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ?Order $order = null): bool
    {
        return $this->owns($user, $order);
    }

    public function print(User $user, ?Order $order = null): bool
    {
        return $this->owns($user, $order);
    }

    public function delete(User $user, ?Order $order = null): bool
    {
        return $user->role === 'admin';
    }

    public function deleteAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function restore(User $user, ?Order $order = null): bool
    {
        return $user->role === 'admin';
    }

    public function restoreAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function forceDelete(User $user, ?Order $order = null): bool
    {
        return $user->role === 'admin';
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    private function owns(User $user, ?Order $order): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role !== 'cashier') {
            return false;
        }

        return $order === null || (string) $order->user_uuid === (string) $user->uuid;
    }
}
