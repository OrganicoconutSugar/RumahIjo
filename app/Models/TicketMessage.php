<?php

namespace App\Models;

use App\Services\PortalActivity;
use Illuminate\Database\Eloquent\Model;

class TicketMessage extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['attachments'];

    protected function casts(): array
    {
        return ['attachments' => 'array', 'read_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::created(function (TicketMessage $message) {
            $ticket = Ticket::find($message->ticket_id);
            if ($ticket && $message->sender_id !== $ticket->user_id) {
                PortalActivity::notify($ticket->user_id, 'ticket-reply-'.$message->id, 'Balasan tiket baru', 'Ada balasan untuk tiket '.$ticket->subject.'.', '/penyewa/tiket/'.$ticket->id, 'ticket');
            }
        });
    }
}
