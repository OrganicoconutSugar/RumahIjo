<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TicketPolicy
{
    public function view(User $user, Ticket $model): Response
    {
        return $user->role === 'tenant' && $user->is_active && ((int) $model->user_id === $user->id) ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, Ticket $model): Response
    {
        return $this->view($user, $model);
    }
}
