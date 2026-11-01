<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantProfile extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['nik', 'ktp_path'];

    public function completion(User $user): int
    {
        $fields = ['full_name', 'gender', 'birth_place', 'birth_date', 'nik', 'occupation', 'origin_address', 'emergency_name', 'emergency_relation', 'emergency_phone', 'ktp_path'];
        $filled = $user->phone ? 1 : 0;
        foreach ($fields as $field) {
            if ($this->$field) {
                $filled++;
            }
        }

        return (int) round($filled / 12 * 100);
    }

    protected function casts(): array
    {
        return ['nik' => 'encrypted', 'consent_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
