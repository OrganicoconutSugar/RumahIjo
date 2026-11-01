<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InvoicePolicy
{
    public function view(User $user, Invoice $model): Response
    {
        return $user->role === 'tenant' && $user->is_active && ($model->tenantId() === $user->id) ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, Invoice $model): Response
    {
        return $this->view($user, $model);
    }
}
