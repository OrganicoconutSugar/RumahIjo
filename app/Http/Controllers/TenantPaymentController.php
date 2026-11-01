<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PortalActivity;
use App\Services\TenantBilling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TenantPaymentController extends Controller
{
    public function show(Request $request, Invoice $invoice)
    {
        Gate::authorize('view', $invoice);
        $payments = Payment::where('invoice_id', $invoice->id)->latest('id')->get()->map(fn ($p) => ['id' => $p->id, 'order_id' => $p->order_id, 'status' => $p->status, 'gross_amount' => $p->gross_amount, 'is_simulation' => $p->is_simulation, 'payment_type' => $p->payment_type, 'created_at' => $p->created_at->toIso8601String(), 'review_note' => $p->review_note, 'proof_url' => $p->proof_path ? URL::temporarySignedRoute('tenant.payment.proof', now()->addMinutes(10), ['payment' => $p->id]) : null]);

        return Inertia::render('PaymentDetail', ['invoice' => $invoice, 'payments' => $payments, 'qris' => ['image' => config('portal.qris_image'), 'merchant' => config('portal.qris_merchant')], 'simulation' => app()->environment('local', 'testing') && config('portal.simulation_enabled')]);
    }

    public function submit(Request $request, Invoice $invoice)
    {
        Gate::authorize('update', $invoice);
        $request->validate(['proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:3072', 'paid_amount' => 'required|integer|in:'.$invoice->amount, 'payment_reference' => 'required|string|max:100', 'acknowledge' => 'accepted']);
        DB::transaction(function () use ($request, $invoice) {
            $item = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if (in_array($item->status, ['paid', 'cancelled'])) {
                throw ValidationException::withMessages(['payment' => 'Tagihan ini sudah lunas atau dibatalkan.']);
            }
            if (Payment::where('invoice_id', $item->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['payment' => 'Bukti pembayaran masih menunggu verifikasi.']);
            }
            $path = $request->file('proof')->store('payment-proofs', 'local');
            try {
                $payment = Payment::create(['invoice_id' => $item->id, 'gateway' => 'qris_static', 'order_id' => 'RIJ-'.Str::uuid(), 'gross_amount' => $item->amount, 'payment_type' => 'qris', 'transaction_id' => $request->string('payment_reference')->toString(), 'status' => 'pending', 'proof_path' => $path, 'submitted_at' => now()]);
                $item->forceFill(['status' => 'pending'])->save();
                PortalActivity::audit($request->user()->id, 'payment.submitted', 'payment', $payment->id, ['proof_path', 'status']);
                PortalActivity::notify($request->user()->id, 'payment-submitted-'.$payment->id, 'Bukti pembayaran diterima', 'Pembayaran tagihan '.$item->number.' menunggu verifikasi pemilik.', '/penyewa/pembayaran/'.$item->id);
            } catch (\Throwable $e) {
                Storage::disk('local')->delete($path);
                throw $e;
            }
        });

        return back()->with('success', 'Bukti diterima. Menunggu verifikasi pemilik.');
    }

    public function proof(Request $request, Payment $payment)
    {
        Gate::authorize('view', $payment);
        abort_unless($payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->download($payment->proof_path, 'bukti-pembayaran.'.pathinfo($payment->proof_path, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function simulate(Request $request, Invoice $invoice, TenantBilling $billing)
    {
        Gate::authorize('update', $invoice);
        $data = $request->validate(['result' => 'required|in:paid,failed,expired']);
        $billing->simulate($invoice, $data['result']);

        return back()->with('success', 'Simulasi selesai. Tidak ada uang yang dipindahkan.');
    }

    public function receipt(Request $request, Invoice $invoice)
    {
        Gate::authorize('view', $invoice);
        abort_unless($invoice->status === 'paid', 404);
        $payment = Payment::where('invoice_id', $invoice->id)->where('status', 'paid')->latest('id')->first();

        return Inertia::render('Receipt', ['invoice' => $invoice, 'payment' => $payment?->only(['order_id', 'payment_type', 'paid_at', 'gross_amount', 'is_simulation']), 'payer' => $request->user()->name]);
    }
}
