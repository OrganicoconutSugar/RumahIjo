<?php

namespace App\Policies;

use App\Models\TenantProfile;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TenantProfilePolicy
{
    public function view(User $user, TenantProfile $model): Response
    {
        return $user->role === 'tenant' && $user->is_active && ((int) $model->user_id === $user->id) ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, TenantProfile $model): Response
    {
        return $this->view($user, $model);
    }
}
