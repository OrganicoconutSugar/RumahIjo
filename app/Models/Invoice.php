<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Invoice extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_date' => 'date:Y-m-d', 'period_start' => 'date:Y-m-d', 'period_end' => 'date:Y-m-d', 'paid_at' => 'datetime:Y-m-d H:i:s', 'amount' => 'integer'];
    }

    public function tenantId(): ?int
    {
        return DB::table('tenancies')->where('id', $this->tenancy_id)->value('user_id');
    }
}
