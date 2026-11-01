<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public function portalData(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'phone' => $this->phone, 'theme' => $this->theme, 'locale' => $this->locale, 'must_change_password' => (bool) $this->must_change_password, 'email_invoice_notifications' => (bool) $this->email_invoice_notifications, 'email_ticket_notifications' => (bool) $this->email_ticket_notifications, 'avatar_url' => $this->avatar_path ? '/penyewa/foto?v='.substr(hash('sha256', $this->avatar_path), 0, 12) : null];
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'email_invoice_notifications' => 'boolean',
            'email_ticket_notifications' => 'boolean',
        ];
    }
}
