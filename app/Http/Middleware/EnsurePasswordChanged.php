<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->must_change_password && ! $request->is('penyewa/pengaturan', 'penyewa/pengaturan/password', 'penyewa/foto')) {
            return redirect('/penyewa/pengaturan')->with('warning', 'Silakan ganti password awal sebelum melanjutkan.');
        }

        return $next($request);
    }
}
