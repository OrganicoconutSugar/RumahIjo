<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class QrisPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const STATIC_PAYLOAD = '00020101021126580013ID.CO.BRI.WWW01189360000200701256220208701256220303UMI51440014ID.CO.QRIS.WWW0215ID10264791656500303UMI5204581453033605802ID590799Foods6009PALEMBANG61053012162070703A0163045663';

    private function invoice(User $user, array $attributes = []): Invoice
    {
        $type = DB::table('room_types')->insertGetId(['name' => 'Standar', 'price_monthly' => 850000]);
        $room = DB::table('rooms')->insertGetId(['room_type_id' => $type, 'number' => 'QRIS-'.random_int(100000, 999999), 'floor' => 1, 'status' => 'occupied']);
        $tenancy = DB::table('tenancies')->insertGetId(['user_id' => $user->id, 'room_id' => $room, 'start_date' => '2026-10-01', 'end_date' => '2027-12-31', 'due_day' => 5, 'monthly_price' => 850000, 'deposit_amount' => 0, 'status' => 'active']);

        return Invoice::create(['tenancy_id' => $tenancy, 'number' => 'QRIS-'.random_int(100000, 999999), 'type' => 'rent', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'amount' => 850000, 'due_date' => '2026-11-05', 'status' => 'unpaid', ...$attributes]);
    }

    private function submission(string $reference): array
    {
        return ['proof' => UploadedFile::fake()->image('proof.png'), 'paid_amount' => 850000, 'payment_reference' => $reference, 'acknowledge' => true];
    }

    public function test_payment_page_embeds_the_invoice_amount_in_the_qris_payload(): void
    {
        config(['portal.qris_payload' => self::STATIC_PAYLOAD]);
        $user = User::factory()->create(['role' => 'tenant'])->refresh();
        $invoice = $this->invoice($user);

        $this->actingAs($user)->get('/penyewa/pembayaran/'.$invoice->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('qris.payload', '00020101021226580013ID.CO.BRI.WWW01189360000200701256220208701256220303UMI51440014ID.CO.QRIS.WWW0215ID10264791656500303UMI5204581453033605406850000'.'5802ID590799Foods6009PALEMBANG61053012162070703A0163041026')->where('qris.image', '/payments/qris-rumahijo.jpeg'));
    }

    public function test_payment_page_falls_back_to_the_static_image_when_no_payload_is_configured(): void
    {
        config(['portal.qris_payload' => null]);
        $user = User::factory()->create(['role' => 'tenant'])->refresh();
        $invoice = $this->invoice($user);

        $this->actingAs($user)->get('/penyewa/pembayaran/'.$invoice->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('qris.payload', null)->where('qris.image', '/payments/qris-rumahijo.jpeg'));
    }

    public function test_payment_page_has_no_payload_for_a_paid_invoice(): void
    {
        config(['portal.qris_payload' => self::STATIC_PAYLOAD]);
        $user = User::factory()->create(['role' => 'tenant'])->refresh();
        $invoice = $this->invoice($user, ['status' => 'paid']);

        $this->actingAs($user)->get('/penyewa/pembayaran/'.$invoice->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('qris.payload', null));
    }

    public function test_payment_page_reports_an_invalid_payload_and_still_renders_with_the_static_image(): void
    {
        Exceptions::fake();
        config(['portal.qris_payload' => 'bukan-payload-qris']);
        $user = User::factory()->create(['role' => 'tenant'])->refresh();
        $invoice = $this->invoice($user);

        $this->actingAs($user)->get('/penyewa/pembayaran/'.$invoice->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('qris.payload', null));

        Exceptions::assertReported(InvalidArgumentException::class);
    }

    public function test_reference_already_used_by_a_pending_payment_is_rejected_for_another_invoice(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'tenant'])->refresh();
        $first = $this->invoice($user);
        $second = $this->invoice($user);
        $this->actingAs($user)->post('/penyewa/pembayaran/'.$first->id.'/bukti', $this->submission('REF-SAMA'))->assertRedirect();

        $this->post('/penyewa/pembayaran/'.$second->id.'/bukti', $this->submission('REF-SAMA'))->assertSessionHasErrors(['payment_reference' => 'Nomor referensi ini sudah dipakai pada pembayaran lain.']);

        $this->assertSame(1, Payment::count());
        $this->assertSame('unpaid', $second->fresh()->status);
    }

    public function test_reference_reuse_is_detected_regardless_of_case_and_surrounding_spaces(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'tenant'])->refresh();
        $first = $this->invoice($user);
        $second = $this->invoice($user);
        $this->actingAs($user)->post('/penyewa/pembayaran/'.$first->id.'/bukti', $this->submission('ref-abc123'))->assertRedirect();

        $this->post('/penyewa/pembayaran/'.$second->id.'/bukti', $this->submission('  REF-abc123 '))->assertSessionHasErrors('payment_reference');

        $this->assertSame('REF-ABC123', Payment::first()->transaction_id);
        $this->assertSame(1, Payment::count());
    }

    public function test_reference_of_a_rejected_payment_can_be_submitted_again(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'tenant'])->refresh();
        $invoice = $this->invoice($user);
        Payment::create(['invoice_id' => $invoice->id, 'gateway' => 'qris_static', 'order_id' => 'OLD', 'gross_amount' => 850000, 'status' => 'failed', 'transaction_id' => 'REF-ULANG']);

        $this->actingAs($user)->post('/penyewa/pembayaran/'.$invoice->id.'/bukti', $this->submission('REF-ULANG'))->assertRedirect();

        $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'status' => 'pending', 'transaction_id' => 'REF-ULANG']);
    }
}
