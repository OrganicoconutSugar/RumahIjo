<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }
}
