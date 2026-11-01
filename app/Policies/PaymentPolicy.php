<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PaymentPolicy
{
    public function view(User $user, Payment $model): Response
    {
        return $user->role === 'tenant' && $user->is_active && ($model->invoice?->tenantId() === $user->id) ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, Payment $model): Response
    {
        return $this->view($user, $model);
    }
}
