<?php

use App\Http\Controllers\CalendarEventController;
use App\Http\Controllers\TenantPaymentController;
use App\Http\Controllers\TenantPortalController;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Landing', ['roomTypes' => DB::table('room_types')->select('name', 'price_monthly', 'facilities')->orderBy('id')->get(), 'availability' => DB::table('rooms')->select('number', 'status')->orderBy('number')->get(), 'loggedIn' => Auth::check(), 'dashboardUrl' => Auth::check() && Auth::user()->role === 'owner' ? '/pemilik/dashboard' : '/penyewa/dashboard']));
Route::get('/api/kamar/ketersediaan', fn () => response()->json(DB::table('rooms')->select('number', 'status')->orderBy('number')->get()));
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => Inertia::render('Login'))->name('login');
    Route::post('/login', function (Request $request) {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $key = Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.']);
        }
        if (! Auth::attempt([...$data, 'is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Email atau password salah.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();

        return redirect($request->user()->role === 'tenant' ? '/penyewa/dashboard' : '/pemilik/dashboard');
    });
});
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/');
})->middleware('auth');
Route::get('/pemilik/dashboard', function (Request $request) {
    abort_unless($request->user()->role === 'owner' && $request->user()->is_active, 403);

    return response('Portal pemilik tersedia pada fase 3.', 200);
})->middleware('auth');
Route::middleware(['auth', EnsureTenant::class, EnsurePasswordChanged::class])->prefix('penyewa')->group(function () {
    Route::get('/berkas/ktp', [TenantPortalController::class, 'ktp'])->middleware('signed')->name('tenant.ktp');
    Route::post('/biodata/ktp', [TenantPortalController::class, 'ktpUpload']);
    Route::put('/biodata', [TenantPortalController::class, 'profile']);
    Route::post('/tiket', [TenantPortalController::class, 'createTicket']);
    Route::get('/tiket/{ticket}', [TenantPortalController::class, 'ticket'])->whereNumber('ticket');
    Route::post('/tiket/{ticket}/pesan', [TenantPortalController::class, 'message'])->whereNumber('ticket')->middleware('throttle:20,1');
    Route::patch('/tiket/{ticket}/status', [TenantPortalController::class, 'ticketStatus'])->whereNumber('ticket');
    Route::put('/pengaturan/profil', [TenantPortalController::class, 'settings']);
    Route::put('/pengaturan/password', [TenantPortalController::class, 'password']);
    Route::get('/foto', [TenantPortalController::class, 'avatar']);
    Route::post('/pengaturan/foto', [TenantPortalController::class, 'avatarUpload'])->middleware('throttle:10,1');
    Route::get('/tiket/{ticket}/lampiran/{message}/{index}', [TenantPortalController::class, 'attachment'])->middleware('signed')->name('tenant.ticket.attachment');
    Route::patch('/notifikasi/{notification}', [TenantPortalController::class, 'readNotification']);
    Route::get('/pembayaran/{invoice}', [TenantPaymentController::class, 'show'])->whereNumber('invoice');
    Route::post('/pembayaran/{invoice}/bukti', [TenantPaymentController::class, 'submit'])->middleware('throttle:10,1');
    Route::post('/pembayaran/{invoice}/simulasi', [TenantPaymentController::class, 'simulate'])->middleware('throttle:10,1');
    Route::get('/pembayaran/{invoice}/kuitansi', [TenantPaymentController::class, 'receipt']);
    Route::get('/berkas/pembayaran/{payment}', [TenantPaymentController::class, 'proof'])->middleware('signed')->name('tenant.payment.proof');
    Route::post('/agenda', [CalendarEventController::class, 'store'])->middleware('throttle:20,1');
    Route::put('/agenda/{event}', [CalendarEventController::class, 'update'])->whereNumber('event');
    Route::delete('/agenda/{event}', [CalendarEventController::class, 'destroy'])->whereNumber('event');
    Route::get('/{page}', [TenantPortalController::class, 'show']);
});
