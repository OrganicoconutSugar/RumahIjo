<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantBilling
{
    public function review(Payment $payment, bool $approve, string $note = ''): Payment
    {
        return DB::transaction(function () use ($payment, $approve, $note) {
            $invoice = Invoice::whereKey($payment->invoice_id)->lockForUpdate()->firstOrFail();
            $item = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($item->status === 'paid' && $approve) {
                return $item;
            }
            if ($item->status !== 'pending' || $invoice->status === 'paid') {
                throw ValidationException::withMessages(['payment' => 'Pembayaran ini sudah diproses.']);
            }
            $item->forceFill(['status' => $approve ? 'paid' : 'failed', 'paid_at' => $approve ? now() : null, 'reviewed_at' => now(), 'review_note' => $note])->save();
            $invoice->forceFill(['status' => $approve ? 'paid' : ($invoice->due_date->lt(today()) ? 'overdue' : 'unpaid'), 'paid_at' => $approve ? now() : null])->save();
            PortalActivity::audit(null, $approve ? 'payment.approved' : 'payment.rejected', 'payment', $item->id, ['status', 'reviewed_at', 'review_note']);
            PortalActivity::notify($invoice->tenantId(), 'payment-review-'.$item->id, $approve ? 'Pembayaran terverifikasi' : 'Bukti pembayaran perlu diperbaiki', $approve ? 'Tagihan '.$invoice->number.' sudah lunas.' : 'Bukti tagihan '.$invoice->number.' belum disetujui. '.($note ?: 'Silakan hubungi pemilik.'), '/penyewa/pembayaran/'.$invoice->id);

            return $item;
        });
    }

    public function simulate(Invoice $invoice, string $result): Payment
    {
        abort_unless(app()->environment('local', 'testing') && config('portal.simulation_enabled'), 404);

        return DB::transaction(function () use ($invoice, $result) {
            $item = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if (in_array($item->status, ['paid', 'cancelled']) || Payment::where('invoice_id', $item->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['payment' => 'Tagihan sudah lunas atau masih menunggu verifikasi.']);
            }
            $payment = Payment::create(['invoice_id' => $item->id, 'gateway' => 'fake', 'order_id' => 'SIM-'.Str::uuid(), 'gross_amount' => $item->amount, 'status' => 'pending', 'payment_type' => 'simulation', 'is_simulation' => true, 'submitted_at' => now()]);
            if ($result === 'paid') {
                return $this->review($payment, true, 'Simulasi lokal, bukan pembayaran nyata.');
            }
            $payment->forceFill(['status' => $result === 'expired' ? 'expired' : 'failed'])->save();
            PortalActivity::audit(null, 'payment.simulated', 'payment', $payment->id, ['status']);

            return $payment;
        });
    }
}
