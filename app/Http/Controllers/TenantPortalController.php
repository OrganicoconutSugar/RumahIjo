<?php

namespace App\Http\Controllers;

use App\Models\TenantProfile;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Services\PortalActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class TenantPortalController extends Controller
{
    private function tenancy(Request $request)
    {
        return DB::table('tenancies')->join('rooms', 'rooms.id', '=', 'tenancies.room_id')->join('room_types', 'room_types.id', '=', 'rooms.room_type_id')->where('tenancies.user_id', $request->user()->id)->whereIn('tenancies.status', ['active', 'pending'])->select('tenancies.*', 'rooms.number', 'rooms.floor', 'room_types.name as room_type', 'room_types.facilities')->first();
    }

    private function invoices(Request $request)
    {
        return DB::table('invoices')->join('tenancies', 'tenancies.id', '=', 'invoices.tenancy_id')->where('tenancies.user_id', $request->user()->id)->select('invoices.*')->orderByDesc('due_date')->get();
    }

    public function show(Request $request, string $page)
    {
        abort_unless(in_array($page, ['dashboard', 'kalender', 'kamar', 'biodata', 'pembayaran', 'tiket', 'pengaturan']), 404);
        $user = $request->user();
        $model = TenantProfile::where('user_id', $user->id)->first();
        $profile = null;
        if ($model) {
            Gate::authorize('view', $model);
            $profile = $model->toArray();
            $nik = $model->nik;
            $profile['nik_masked'] = $nik ? substr($nik, 0, 4).'••••••••'.substr($nik, -4) : null;
            $profile['ktp_url'] = $model->ktp_path ? URL::temporarySignedRoute('tenant.ktp', now()->addMinutes(10)) : null;
        }
        $tickets = DB::table('tickets')->where('user_id', $user->id)->orderByDesc('last_message_at')->get();
        $unread = DB::table('ticket_messages')->whereIn('ticket_id', $tickets->pluck('id'))->where('sender_id', '!=', $user->id)->whereNull('read_at')->selectRaw('ticket_id, count(*) as total')->groupBy('ticket_id')->pluck('total', 'ticket_id');
        foreach ($tickets as $ticket) {
            $ticket->unread_count = (int) ($unread[$ticket->id] ?? 0);
        }

        return Inertia::render('Dashboard', ['events' => $page === 'kalender' ? DB::table('calendar_events')->where('user_id', $user->id)->orderBy('starts_at')->get() : [], 'page' => $page, 'user' => $user->portalData(), 'tenancy' => $this->tenancy($request), 'profile' => $profile, 'completion' => $model?->completion($user) ?? 0, 'invoices' => $this->invoices($request), 'tickets' => $tickets, 'announcements' => DB::table('announcements')->where('published_at', '<=', now())->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->orderByDesc('published_at')->limit(3)->get()]);
    }

    public function profile(Request $request)
    {
        $profile = TenantProfile::firstOrNew(['user_id' => $request->user()->id]);
        Gate::authorize('update', $profile);
        $data = $request->validate(['full_name' => 'required|string|max:255', 'gender' => 'required|in:male,female', 'birth_place' => 'required|string|max:255', 'birth_date' => 'required|date|before:today', 'nik' => ($profile->nik ? 'nullable' : 'required').'|digits:16', 'occupation' => 'required|string|max:255', 'institution' => 'nullable|string|max:255', 'origin_address' => 'required|string|max:2000', 'emergency_name' => 'required|string|max:255', 'emergency_relation' => 'required|string|max:255', 'emergency_phone' => 'required|string|max:30', 'phone' => 'required|string|max:30', 'consent' => 'accepted']);
        unset($data['consent']);
        $phone = $data['phone'];
        unset($data['phone']);
        if (empty($data['nik'])) {
            unset($data['nik']);
        }
        DB::transaction(function () use ($request, $data, $profile, $phone) {
            $profile->fill($data);
            $changed = array_keys($profile->getDirty());
            $profile->consent_at ??= now();
            $profile->completed_at = $profile->completion($request->user()->forceFill(['phone' => $phone])) === 100 ? ($profile->completed_at ?? now()) : null;
            $profile->save();
            $request->user()->forceFill(['phone' => $phone])->save();
            if ($changed) {
                PortalActivity::audit($request->user()->id, 'profile.updated', 'profile', $profile->id, $changed);
            }
        });

        return back()->with('success', 'Biodata berhasil disimpan.');
    }

    public function ktpUpload(Request $request)
    {
        $request->validate(['ktp' => 'required|file|mimes:jpg,jpeg,png,pdf|max:3072', 'consent' => 'accepted']);
        $profile = TenantProfile::firstOrNew(['user_id' => $request->user()->id]);
        Gate::authorize('update', $profile);
        $old = $profile->ktp_path;
        $path = $request->file('ktp')->store('ktp', 'local');
        try {
            DB::transaction(function () use ($request, $profile, $path) {
                $profile->ktp_path = $path;
                $profile->consent_at ??= now();
                $profile->completed_at = $profile->completion($request->user()) === 100 ? ($profile->completed_at ?? now()) : null;
                $profile->save();
                PortalActivity::audit($request->user()->id, 'profile.ktp_updated', 'profile', $profile->id, ['ktp_path']);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }if ($old) {
            Storage::disk('local')->delete($old);
        }

        return back()->with('success', 'KTP tersimpan secara privat.');
    }

    public function ktp(Request $request)
    {
        $profile = TenantProfile::where('user_id', $request->user()->id)->firstOrFail();
        Gate::authorize('view', $profile);
        abort_unless($profile->ktp_path && Storage::disk('local')->exists($profile->ktp_path), 404);

        return Storage::disk('local')->download($profile->ktp_path, 'ktp.'.pathinfo($profile->ktp_path, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function avatar(Request $request)
    {
        $path = $request->user()->avatar_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function avatarUpload(Request $request)
    {
        $request->validate(['avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048|dimensions:max_width=4096,max_height=4096']);
        $image = imagecreatefromstring($request->file('avatar')->get());
        abort_unless($image, 422);
        $w = imagesx($image);
        $h = imagesy($image);
        $size = min($w, $h);
        $square = imagecreatetruecolor(400, 400);
        imagecopyresampled($square, $image, 0, 0, (int) (($w - $size) / 2), (int) (($h - $size) / 2), 400, 400, $size, $size);
        ob_start();
        imagejpeg($square, null, 88);
        $bytes = ob_get_clean();
        imagedestroy($square);
        imagedestroy($image);
        $path = 'avatars/'.Str::uuid().'.jpg';
        Storage::disk('local')->put($path, $bytes);
        $old = $request->user()->avatar_path;
        try {
            DB::transaction(function () use ($request, $path) {
                $request->user()->forceFill(['avatar_path' => $path])->save();
                PortalActivity::audit($request->user()->id, 'account.avatar_updated', 'user', $request->user()->id, ['avatar_path']);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }if ($old) {
            Storage::disk('local')->delete($old);
        }

        return back()->with('success', 'Foto profil berhasil diperbarui.');
    }

    public function ticket(Request $request, Ticket $ticket)
    {
        Gate::authorize('view', $ticket);
        DB::table('ticket_messages')->where('ticket_id', $ticket->id)->where('sender_id', '!=', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        $messages = DB::table('ticket_messages')->join('users', 'users.id', '=', 'ticket_messages.sender_id')->where('ticket_id', $ticket->id)->select('ticket_messages.*', 'users.name')->orderBy('ticket_messages.id')->get()->map(function ($m) use ($ticket) {
            $paths = json_decode($m->attachments ?? '[]', true);
            $m->attachments = array_map(fn ($index) => URL::temporarySignedRoute('tenant.ticket.attachment', now()->addMinutes(10), ['ticket' => $ticket->id, 'message' => $m->id, 'index' => $index]), array_keys($paths));

            return $m;
        });

        return Inertia::render('Ticket', ['ticket' => $ticket, 'messages' => $messages, 'user' => $request->user()->portalData(), 'canReopen' => $ticket->status === 'closed' && $ticket->closed_at?->betweenIncluded(now()->subDays(7), now())]);
    }

    public function createTicket(Request $request)
    {
        $data = $request->validate(['subject' => 'required|string|max:150', 'category' => 'required|in:Fasilitas,Pembayaran,Kebersihan,Perpanjangan,Lainnya', 'priority' => 'required|in:normal,tinggi', 'body' => 'required|string|max:5000']);
        $ticket = DB::transaction(function () use ($request, $data) {
            $ticket = Ticket::create(['user_id' => $request->user()->id, 'subject' => $data['subject'], 'category' => $data['category'], 'priority' => $data['priority'], 'status' => 'open', 'last_message_at' => now()]);
            TicketMessage::create(['ticket_id' => $ticket->id, 'sender_id' => $request->user()->id, 'body' => $data['body']]);

            return $ticket;
        });

        return redirect('/penyewa/tiket/'.$ticket->id);
    }

    public function message(Request $request, Ticket $ticket)
    {
        Gate::authorize('update', $ticket);
        $request->validate(['body' => 'required_without:attachments|nullable|string|max:5000', 'attachments' => 'nullable|array|max:3', 'attachments.*' => 'image|mimes:jpg,jpeg,png|max:2048']);
        $paths = [];
        try {
            DB::transaction(function () use ($request, $ticket, &$paths) {
                $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
                abort_if($locked->status === 'closed', 422, 'Tiket sudah ditutup.');
                foreach ($request->file('attachments', []) as $file) {
                    $paths[] = $file->store('ticket-attachments', 'local');
                }TicketMessage::create(['ticket_id' => $ticket->id, 'sender_id' => $request->user()->id, 'body' => $request->input('body') ?: '', 'attachments' => $paths]);
                $locked->forceFill(['last_message_at' => now()])->save();
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($paths);
            throw $e;
        }

        return back();
    }

    public function attachment(Request $request, Ticket $ticket, int $message, int $index)
    {
        Gate::authorize('view', $ticket);
        $item = TicketMessage::where('ticket_id', $ticket->id)->findOrFail($message);
        $path = ($item->attachments ?? [])[$index] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function ticketStatus(Request $request, Ticket $ticket)
    {
        Gate::authorize('update', $ticket);
        $data = $request->validate(['status' => 'required|in:open,closed']);
        DB::transaction(function () use ($ticket, $data, $request) {
            $item = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if ($data['status'] === 'open') {
                abort_unless($item->status === 'closed' && $item->closed_at?->betweenIncluded(now()->subDays(7), now()), 422);
            } else {
                abort_if($item->status === 'closed', 422);
            }$item->forceFill(['status' => $data['status'], 'closed_at' => $data['status'] === 'closed' ? now() : null])->save();
            PortalActivity::audit($request->user()->id, 'ticket.status_updated', 'ticket', $item->id, ['status']);
        });

        return back();
    }

    public function settings(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'phone' => 'nullable|string|max:30', 'theme' => 'required|in:light,dark,system', 'email_invoice_notifications' => 'required|boolean', 'email_ticket_notifications' => 'required|boolean']);
        $request->user()->forceFill($data)->save();

        return back()->with('success', 'Pengaturan tersimpan.');
    }

    public function password(Request $request)
    {
        $data = $request->validate(['current_password' => 'required|current_password', 'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers(), 'different:current_password']]);
        DB::transaction(function () use ($request, $data) {
            $request->user()->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false])->save();
            PortalActivity::audit($request->user()->id, 'account.password_changed', 'user', $request->user()->id, ['password']);
        });
        $request->session()->regenerate();

        return back()->with('success', 'Password berhasil diubah.');
    }

    public function readNotification(Request $request, string $notification)
    {
        $item = $request->user()->notifications()->where('id', $notification)->firstOrFail();
        $item->markAsRead();

        return back();
    }
}
