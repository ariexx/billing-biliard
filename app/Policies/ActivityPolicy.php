<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Spatie\Activitylog\Models\Activity;

class ActivityPolicy
{
	use HandlesAuthorization;

	public function viewAny(User $user): bool
	{
        return $user->role === 'admin';
	}

	public function view(User $user): bool
	{
        return $user->role === 'admin';
	}

	public function create(User $user): bool
	{
	}

	public function update(User $user): bool
	{
        return $user->role === 'admin';
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
