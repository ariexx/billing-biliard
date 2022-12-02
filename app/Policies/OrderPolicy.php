<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderPolicy
{
	use HandlesAuthorization;

	public function viewAny(User $user): bool
	{
        return in_array($user->role, ['admin', 'cashier']);
	}

	public function view(User $user): bool
	{
        return in_array($user->role, ['admin', 'cashier']);
	}

	public function create(User $user): bool
	{
        return $user->role === 'admin';
	}

	public function update(User $user): bool
	{
        return in_array($user->role, ['admin', 'cashier']);
	}

	public function delete(User $user): bool
	{
        return $user->role === 'admin';
	}

	public function restore(User $user): bool
	{
        return $user->role === 'admin';
	}

	public function forceDelete(User $user): bool
	{
        return $user->role === 'admin';
	}
}
