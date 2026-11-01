<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PaymentDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(User $user, array $attributes = []): Invoice
    {
        $type = DB::table('room_types')->insertGetId(['name' => 'Standar', 'price_monthly' => 850000]);
        $room = DB::table('rooms')->insertGetId(['room_type_id' => $type, 'number' => 'DOC-'.random_int(100000, 999999), 'floor' => 1, 'status' => 'occupied']);
        $tenancy = DB::table('tenancies')->insertGetId(['user_id' => $user->id, 'room_id' => $room, 'start_date' => '2026-10-01', 'end_date' => '2027-12-31', 'due_day' => 5, 'monthly_price' => 850000, 'deposit_amount' => 0, 'status' => 'active']);

        return Invoice::create(['tenancy_id' => $tenancy, 'number' => 'DOC-'.random_int(100000, 999999), 'type' => 'rent', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'amount' => 850000, 'due_date' => '2026-11-05', 'status' => 'unpaid', ...$attributes]);
    }

    private function payment(Invoice $invoice, array $attributes = []): Payment
    {
        return Payment::create(['invoice_id' => $invoice->id, 'gateway' => 'qris_static', 'order_id' => 'ORD-'.$invoice->id.'-'.random_int(1000, 9999), 'gross_amount' => 850000, 'status' => 'pending', ...$attributes]);
    }

    public function test_receipt_renders_paid_invoice_for_its_tenant(): void
    {
        $user = User::factory()->create(['role' => 'tenant', 'name' => 'Budi Santoso'])->refresh();
        $invoice = $this->invoice($user, ['status' => 'paid', 'paid_at' => '2026-11-03 10:00:00']);
        $this->payment($invoice, ['status' => 'paid', 'order_id' => 'RIJ-RECEIPT', 'paid_at' => '2026-11-03 10:00:00']);

        $this->actingAs($user)->get('/penyewa/pembayaran/'.$invoice->id.'/kuitansi')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Receipt')->where('invoice.number', $invoice->number)->where('payer', 'Budi Santoso')->where('payment.order_id', 'RIJ-RECEIPT')->where('payment.gross_amount', 850000)->where('payment.is_simulation', false));
    }

    public function test_receipt_returns_404_for_another_tenants_paid_invoice(): void
    {
        $owner = User::factory()->create(['role' => 'tenant'])->refresh();
        $other = User::factory()->create(['role' => 'tenant'])->refresh();
        $invoice = $this->invoice($owner, ['status' => 'paid']);
        $this->payment($invoice, ['status' => 'paid']);

        $this->actingAs($other)->get('/penyewa/pembayaran/'.$invoice->id.'/kuitansi')->assertNotFound();
    }

    public function test_receipt_requires_login(): void
    {
        $invoice = $this->invoice(User::factory()->create(['role' => 'tenant'])->refresh(), ['status' => 'paid']);

        $this->get('/penyewa/pembayaran/'.$invoice->id.'/kuitansi')->assertRedirect('/login');
    }

    public function test_proof_download_is_private_and_sent_as_attachment(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'tenant'])->refresh();
        Storage::disk('local')->put('payment-proofs/proof.png', 'bytes');
        $payment = $this->payment($this->invoice($user), ['proof_path' => 'payment-proofs/proof.png']);
        $url = URL::temporarySignedRoute('tenant.payment.proof', now()->addMinutes(5), ['payment' => $payment->id]);

        $response = $this->actingAs($user)->get($url);

        $response->assertOk()->assertDownload('bukti-pembayaran.png');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_proof_download_rejects_unsigned_and_expired_links_with_403(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'tenant'])->refresh();
        Storage::disk('local')->put('payment-proofs/proof.png', 'bytes');
        $payment = $this->payment($this->invoice($user), ['proof_path' => 'payment-proofs/proof.png']);

        $this->actingAs($user)->get('/penyewa/berkas/pembayaran/'.$payment->id)->assertForbidden();
        $this->get(URL::temporarySignedRoute('tenant.payment.proof', now()->subMinute(), ['payment' => $payment->id]))->assertForbidden();
    }

    public function test_proof_download_returns_404_when_payment_has_no_stored_proof(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'tenant'])->refresh();
        $withoutPath = $this->payment($this->invoice($user));
        $missingFile = $this->payment($this->invoice($user), ['proof_path' => 'payment-proofs/gone.png']);

        $this->actingAs($user);

        $this->get(URL::temporarySignedRoute('tenant.payment.proof', now()->addMinutes(5), ['payment' => $withoutPath->id]))->assertNotFound();
        $this->get(URL::temporarySignedRoute('tenant.payment.proof', now()->addMinutes(5), ['payment' => $missingFile->id]))->assertNotFound();
    }

    public function test_pending_command_lists_only_pending_static_qris_payments(): void
    {
        $user = User::factory()->create(['role' => 'tenant'])->refresh();
        $invoice = $this->invoice($user, ['number' => 'RIJ-PENDING']);
        $listed = $this->payment($invoice, ['transaction_id' => 'REF-LISTED', 'proof_path' => 'payment-proofs/listed.png']);
        $this->payment($invoice, ['status' => 'paid', 'transaction_id' => 'REF-PAID']);
        $this->payment($invoice, ['gateway' => 'fake', 'transaction_id' => 'REF-SIM']);

        $this->artisan('payments:pending')
            ->expectsTable(['ID', 'Tagihan', 'Nominal', 'Referensi', 'Bukti privat lokal'], [[$listed->id, 'RIJ-PENDING', 850000, 'REF-LISTED', storage_path('app/private/payment-proofs/listed.png')]])
            ->assertSuccessful();
    }
}
