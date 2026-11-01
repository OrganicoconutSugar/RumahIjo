<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PortalMailNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PortalActivity
{
    public static function audit(?int $actor, string $action, string $type, int $id, array $fields): void
    {
        DB::table('audit_logs')->insert(['actor_id' => $actor, 'action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'changed_fields' => json_encode(array_values($fields)), 'created_at' => now()]);
    }

    public static function notify(int $userId, string $key, string $title, string $body, string $url, string $category = 'invoice'): void
    {
        DB::transaction(function () use ($userId, $key, $title, $body, $url, $category) {
            if (! DB::table('notification_deliveries')->insertOrIgnore(['user_id' => $userId, 'event_key' => $key, 'created_at' => now(), 'updated_at' => now()])) {
                return;
            }
            $payload = compact('title', 'body', 'url', 'category');
            DB::table('notifications')->insert(['id' => (string) Str::uuid(), 'type' => 'portal', 'notifiable_type' => User::class, 'notifiable_id' => $userId, 'data' => json_encode($payload), 'created_at' => now(), 'updated_at' => now()]);
            $user = User::find($userId);
            if ($user && $user->is_active && $user->{'email_'.$category.'_notifications'}) {
                $user->notify(new PortalMailNotification($payload));
            }
        });
    }
}
