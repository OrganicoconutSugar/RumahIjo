<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TenantProfile;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Notifications\PortalMailNotification;
use App\Services\PortalActivity;
use App\Services\TenantBilling;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TenantMaturityTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(array $attributes = []): User
    {
        return User::factory()->create(['role' => 'tenant', ...$attributes])->refresh();
    }

    private function invoice(User $user, array $attributes = []): Invoice
    {
        $type = DB::table('room_types')->insertGetId(['name' => 'Standar', 'price_monthly' => 850000]);
        $room = DB::table('rooms')->insertGetId(['room_type_id' => $type, 'number' => 'TEST-'.random_int(100000, 999999), 'floor' => 1, 'status' => 'occupied']);
        $tenancy = DB::table('tenancies')->insertGetId(['user_id' => $user->id, 'room_id' => $room, 'start_date' => '2026-10-01', 'end_date' => '2027-12-31', 'due_day' => 5, 'monthly_price' => 850000, 'deposit_amount' => 850000, 'status' => 'active']);

        return Invoice::create(['tenancy_id' => $tenancy, 'number' => 'TEST-'.random_int(100000, 999999), 'type' => 'rent', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'amount' => 850000, 'due_date' => '2026-11-05', 'status' => 'unpaid', ...$attributes]);
    }

    public function test_policies_refuse_cross_tenant_and_owner_access(): void
    {
        $one = $this->tenant();
        $two = $this->tenant();
        $owner = $this->tenant(['role' => 'owner']);
        $inactive = $this->tenant(['is_active' => false]);
        $invoice = $this->invoice($one);
        $ticket = Ticket::create(['user_id' => $one->id, 'subject' => 'Lampu', 'category' => 'Fasilitas', 'priority' => 'normal', 'status' => 'open']);
        $profile = TenantProfile::create(['user_id' => $one->id]);
        $payment = Payment::create(['invoice_id' => $invoice->id, 'gateway' => 'qris_static', 'order_id' => 'POLICY', 'gross_amount' => 850000, 'status' => 'pending']);
        foreach ([$invoice, $ticket, $profile, $payment] as $model) {
            $this->assertTrue(Gate::forUser($one)->allows('view', $model));
            foreach ([$two, $owner, $inactive] as $refused) {
                $this->assertFalse(Gate::forUser($refused)->allows('view', $model));
                $this->assertFalse(Gate::forUser($refused)->allows('update', $model));
            }
        }
        $this->actingAs($two)->get('/penyewa/pembayaran/'.$invoice->id)->assertNotFound();
        $this->actingAs($owner)->get('/penyewa/pembayaran/'.$invoice->id)->assertForbidden();
    }

    public function test_initial_password_blocks_portal_until_successful_change(): void
    {
        $user = $this->tenant(['must_change_password' => true, 'password' => Hash::make('Start1234')]);
        $this->actingAs($user)->get('/penyewa/dashboard')->assertRedirect('/penyewa/pengaturan');
        $this->get('/penyewa/pengaturan')->assertOk();
        $this->put('/penyewa/pengaturan/password', ['current_password' => 'wrong', 'password' => 'Better1234', 'password_confirmation' => 'Better1234'])->assertSessionHasErrors('current_password');
        $this->assertTrue($user->fresh()->must_change_password);
        $this->put('/penyewa/pengaturan/password', ['current_password' => 'Start1234', 'password' => 'Better1234', 'password_confirmation' => 'Better1234'])->assertRedirect();
        $this->assertFalse($user->fresh()->must_change_password);
        $this->get('/penyewa/dashboard')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $user->id, 'action' => 'account.password_changed']);
        $this->assertStringNotContainsString('Better1234', DB::table('audit_logs')->value('changed_fields'));
    }

    public function test_avatar_is_private_reencoded_and_updates_only_current_user(): void
    {
        Storage::fake('local');
        $one = $this->tenant();
        $two = $this->tenant();
        $this->actingAs($one)->post('/penyewa/pengaturan/foto', ['avatar' => UploadedFile::fake()->image('photo.png', 600, 450), 'user_id' => $two->id])->assertRedirect();
        $one->refresh();
        Storage::disk('local')->assertExists($one->avatar_path);
        $this->assertStringEndsWith('.jpg', $one->avatar_path);
        $this->assertNull($two->fresh()->avatar_path);
        $this->get('/penyewa/foto')->assertOk();
        $this->actingAs($two)->get('/penyewa/foto')->assertNotFound();
        $this->post('/penyewa/pengaturan/foto', ['avatar' => UploadedFile::fake()->create('evil.svg', 1, 'image/svg+xml')])->assertSessionHasErrors('avatar');
    }

    public function test_ktp_requires_consent_signed_url_and_own_session(): void
    {
        Storage::fake('local');
        $one = $this->tenant();
        $two = $this->tenant();
        $file = UploadedFile::fake()->image('ktp.png');
        $this->actingAs($one)->post('/penyewa/biodata/ktp', ['ktp' => $file])->assertSessionHasErrors('consent');
        $this->post('/penyewa/biodata/ktp', ['ktp' => $file, 'consent' => true])->assertRedirect();
        $path = TenantProfile::where('user_id', $one->id)->value('ktp_path');
        Storage::disk('local')->assertExists($path);
        $this->get('/penyewa/berkas/ktp')->assertForbidden();
        $url = URL::temporarySignedRoute('tenant.ktp', now()->addMinutes(5));
        $this->get($url)->assertOk();
        $this->actingAs($two)->get($url)->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $one->id, 'action' => 'profile.ktp_updated']);
    }

    public function test_qris_proof_stays_pending_and_duplicate_submission_is_rejected(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = $this->tenant();
        $invoice = $this->invoice($user);
        $body = ['proof' => UploadedFile::fake()->image('proof.png'), 'paid_amount' => 850000, 'payment_reference' => 'REF-123', 'acknowledge' => true, 'status' => 'paid'];
        $this->actingAs($user)->post('/penyewa/pembayaran/'.$invoice->id.'/bukti', $body)->assertRedirect();
        $this->assertSame('pending', $invoice->fresh()->status);
        $payment = Payment::first();
        $this->assertSame('pending', $payment->status);
        $this->assertNull($invoice->fresh()->paid_at);
        Storage::disk('local')->assertExists($payment->proof_path);
        $this->post('/penyewa/pembayaran/'.$invoice->id.'/bukti', $body)->assertSessionHasErrors('payment');
        $this->assertSame(1, Payment::count());
        $this->get('/penyewa/pembayaran/'.$invoice->id.'/kuitansi')->assertNotFound();
        $other = $this->tenant();
        $proofUrl = URL::temporarySignedRoute('tenant.payment.proof', now()->addMinutes(5), ['payment' => $payment->id]);
        $this->actingAs($other)->get($proofUrl)->assertNotFound();
        $this->actingAs($user)->get($proofUrl)->assertOk();
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id]);
    }

    public function test_qris_wrong_amount_does_not_create_payment(): void
    {
        $user = $this->tenant();
        $invoice = $this->invoice($user);
        $this->actingAs($user)->post('/penyewa/pembayaran/'.$invoice->id.'/bukti', ['proof' => UploadedFile::fake()->image('proof.png'), 'paid_amount' => 100, 'payment_reference' => 'REF', 'acknowledge' => true])->assertSessionHasErrors('paid_amount');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_review_is_idempotent_and_cannot_downgrade_paid(): void
    {
        Queue::fake();
        $user = $this->tenant();
        $invoice = $this->invoice($user, ['status' => 'pending']);
        $payment = Payment::create(['invoice_id' => $invoice->id, 'gateway' => 'qris_static', 'order_id' => 'REVIEW', 'gross_amount' => 850000, 'status' => 'pending']);
        $this->artisan('payments:review', ['payment' => $payment->id, '--approve' => true])->assertSuccessful();
        $this->artisan('payments:review', ['payment' => $payment->id, '--approve' => true])->assertSuccessful();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(1, DB::table('notifications')->count());
        $this->actingAs($user)->get('/penyewa/pembayaran/'.$invoice->id.'/kuitansi')->assertOk();
        $this->expectException(ValidationException::class);
        app(TenantBilling::class)->review($payment, false);
    }

    public function test_rejected_proof_reopens_overdue_invoice(): void
    {
        Queue::fake();
        $this->travelTo(now()->setDate(2026, 11, 10));
        $invoice = $this->invoice($this->tenant(), ['status' => 'pending']);
        $payment = Payment::create(['invoice_id' => $invoice->id, 'gateway' => 'qris_static', 'order_id' => 'REJECT', 'gross_amount' => 850000, 'status' => 'pending']);
        app(TenantBilling::class)->review($payment, false, 'Nominal tidak cocok');
        $this->assertSame('overdue', $invoice->fresh()->status);
        $this->assertSame('failed', $payment->fresh()->status);
    }

    public function test_simulation_only_runs_locally_and_does_not_make_duplicate_payment(): void
    {
        Queue::fake();
        $user = $this->tenant();
        $invoice = $this->invoice($user);
        $this->actingAs($user)->post('/penyewa/pembayaran/'.$invoice->id.'/simulasi', ['result' => 'paid'])->assertRedirect();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertTrue(Payment::first()->is_simulation);
        $this->post('/penyewa/pembayaran/'.$invoice->id.'/simulasi', ['result' => 'paid'])->assertSessionHasErrors('payment');
        $this->assertSame(1, Payment::count());
        $second = $this->invoice($user);
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->app['env'] = 'production';
        $this->post('/penyewa/pembayaran/'.$second->id.'/simulasi', ['result' => 'paid'])->assertNotFound();
        $this->assertSame('unpaid', $second->fresh()->status);
    }

    public function test_generation_reminders_and_overdue_are_idempotent(): void
    {
        Queue::fake();
        $this->travelTo(now()->setDate(2026, 10, 29)->startOfDay());
        $user = $this->tenant();
        $this->invoice($user, ['due_date' => '2026-10-05']);
        $this->artisan('invoices:generate')->assertSuccessful();
        $this->artisan('invoices:generate')->assertSuccessful();
        $this->assertDatabaseHas('invoices', ['period_start' => '2026-11-01', 'amount' => 850000, 'due_date' => '2026-11-05']);
        $this->assertSame(1, Invoice::where('period_start', '2026-11-01')->count());
        $this->travelTo(now()->setDate(2026, 11, 2));
        $this->artisan('invoices:remind')->assertSuccessful();
        $this->artisan('invoices:remind')->assertSuccessful();
        $this->assertSame(1, DB::table('notification_deliveries')->where('event_key', 'like', 'invoice-reminder-%')->count());
        $this->travelTo(now()->setDate(2026, 11, 6));
        $this->artisan('invoices:mark-overdue')->assertSuccessful();
        $this->artisan('invoices:mark-overdue')->assertSuccessful();
        $this->assertSame('overdue', Invoice::where('period_start', '2026-11-01')->first()->status);
    }

    public function test_ticket_attachments_are_private_and_replies_marked_read(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = $this->tenant();
        $other = $this->tenant();
        $owner = $this->tenant(['role' => 'owner']);
        $ticket = Ticket::create(['user_id' => $user->id, 'subject' => 'Fasilitas', 'category' => 'Fasilitas', 'priority' => 'normal', 'status' => 'open']);
        $this->actingAs($user)->post('/penyewa/tiket/'.$ticket->id.'/pesan', ['body' => 'Foto', 'attachments' => [UploadedFile::fake()->image('lampu.png')]])->assertRedirect();
        $message = TicketMessage::first();
        $url = URL::temporarySignedRoute('tenant.ticket.attachment', now()->addMinutes(5), ['ticket' => $ticket->id, 'message' => $message->id, 'index' => 0]);
        $this->get($url)->assertOk();
        $this->actingAs($other)->get($url)->assertNotFound();
        $reply = TicketMessage::create(['ticket_id' => $ticket->id, 'sender_id' => $owner->id, 'body' => 'Akan dibantu']);
        $this->assertDatabaseHas('notification_deliveries', ['user_id' => $user->id, 'event_key' => 'ticket-reply-'.$reply->id]);
        $this->actingAs($user)->get('/penyewa/tiket/'.$ticket->id)->assertOk();
        $this->assertNotNull($reply->fresh()->read_at);
        $this->post('/penyewa/tiket/'.$ticket->id.'/pesan', ['body' => 'test', 'attachments' => array_fill(0, 4, UploadedFile::fake()->image('x.png'))])->assertSessionHasErrors('attachments');
    }

    public function test_reopening_after_seven_days_fails_and_does_not_extend_deadline(): void
    {
        $user = $this->tenant();
        $ticket = Ticket::create(['user_id' => $user->id, 'subject' => 'Closed', 'category' => 'Lainnya', 'priority' => 'normal', 'status' => 'closed', 'closed_at' => now()->subDays(8)]);
        $this->actingAs($user)->patch('/penyewa/tiket/'.$ticket->id.'/status', ['status' => 'open'])->assertStatus(422);
        $this->patch('/penyewa/tiket/'.$ticket->id.'/status', ['status' => 'closed'])->assertStatus(422);
    }

    public function test_notification_preferences_and_read_actions_are_scoped(): void
    {
        Queue::fake();
        $user = $this->tenant(['email_invoice_notifications' => false]);
        $other = $this->tenant();
        PortalActivity::notify($user->id, 'test', 'Tagihan', 'Pesan', '/penyewa/pembayaran');
        Queue::assertNothingPushed();
        $id = DB::table('notifications')->value('id');
        $this->actingAs($other)->patch('/penyewa/notifikasi/'.$id)->assertNotFound();
        $this->actingAs($user)->patch('/penyewa/notifikasi/'.$id)->assertRedirect();
        $this->assertNotNull(DB::table('notifications')->value('read_at'));
        $this->put('/penyewa/pengaturan/profil', ['name' => 'Nama baru', 'phone' => '08123', 'theme' => 'light', 'email_invoice_notifications' => true, 'email_ticket_notifications' => false, 'role' => 'owner'])->assertRedirect();
        $this->assertSame('tenant', $user->fresh()->role);
        $this->assertTrue($user->fresh()->email_invoice_notifications);
    }

    public function test_message_rate_limit_is_per_user_and_excess_message_is_not_saved(): void
    {
        $user = $this->tenant();
        $ticket = Ticket::create(['user_id' => $user->id, 'subject' => 'Rate limit', 'category' => 'Lainnya', 'priority' => 'normal', 'status' => 'open']);
        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($user)->post('/penyewa/tiket/'.$ticket->id.'/pesan', ['body' => 'Pesan '.$i])->assertRedirect();
        }
        $this->post('/penyewa/tiket/'.$ticket->id.'/pesan', ['body' => 'Lebih dari batas'])->assertStatus(429);
        $this->assertSame(20, TicketMessage::where('ticket_id', $ticket->id)->count());
    }

    public function test_enabled_email_notifications_are_queued_and_deduplicated(): void
    {
        Notification::fake();
        $user = $this->tenant();
        PortalActivity::notify($user->id, 'unique-test', 'Tagihan', 'Pesan', '/penyewa/pembayaran');
        PortalActivity::notify($user->id, 'unique-test', 'Tagihan', 'Pesan', '/penyewa/pembayaran');
        Notification::assertSentToTimes($user, PortalMailNotification::class, 1);
        $this->assertSame(1, DB::table('notifications')->count());
    }

    public function test_failed_and_expired_simulations_do_not_pay_invoice(): void
    {
        $user = $this->tenant();
        $invoice = $this->invoice($user);
        foreach (['failed', 'expired'] as $result) {
            $this->actingAs($user)->post('/penyewa/pembayaran/'.$invoice->id.'/simulasi', ['result' => $result])->assertRedirect();
            $this->assertSame('unpaid', $invoice->fresh()->status);
            $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'status' => $result, 'is_simulation' => true]);
        }
    }

    public function test_expired_ktp_signature_cannot_access_document(): void
    {
        Storage::fake('local');
        $user = $this->tenant();
        $this->actingAs($user)->post('/penyewa/biodata/ktp', ['ktp' => UploadedFile::fake()->image('ktp.png'), 'consent' => true])->assertRedirect();
        $url = URL::temporarySignedRoute('tenant.ktp', now()->subMinute());
        $this->get($url)->assertForbidden();
    }

    public function test_invoice_dates_remain_local_calendar_dates_in_inertia(): void
    {
        $user = $this->tenant();
        $invoice = $this->invoice($user, ['period_start' => '2026-11-01', 'period_end' => '2026-11-30', 'due_date' => '2026-11-05']);
        $this->actingAs($user)->get('/penyewa/pembayaran/'.$invoice->id)->assertInertia(fn ($page) => $page->where('invoice.period_start', '2026-11-01')->where('invoice.period_end', '2026-11-30')->where('invoice.due_date', '2026-11-05'));
    }
}
