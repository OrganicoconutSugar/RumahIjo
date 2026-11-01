<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['proof_path', 'raw_notification'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    protected function casts(): array
    {
        return ['is_simulation' => 'boolean', 'paid_at' => 'datetime', 'gross_amount' => 'integer'];
    }
}
