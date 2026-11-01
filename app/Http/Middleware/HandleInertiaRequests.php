<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [...parent::share($request), 'flash' => ['success' => fn () => $request->session()->get('success'), 'warning' => fn () => $request->session()->get('warning')], 'portal' => function () use ($request) {
            $user = $request->user();
            if (! $user || $user->role !== 'tenant') {
                return null;
            }

            return ['user' => $user->portalData(), 'notifications' => $user->notifications()->latest()->limit(25)->get(['id', 'data', 'read_at', 'created_at']), 'unread_notifications' => $user->unreadNotifications()->count()];
        }];
    }
}
