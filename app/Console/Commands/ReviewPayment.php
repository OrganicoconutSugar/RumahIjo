<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\TenantBilling;
use Illuminate\Console\Command;

class ReviewPayment extends Command
{
    protected $signature = 'payments:review {payment} {--approve} {--reject} {--note=}';

    protected $description = 'Verifikasi pembayaran QRIS setelah mencocokkan dana masuk di aplikasi merchant';

    public function handle(TenantBilling $billing): int
    {
        if ((bool) $this->option('approve') === (bool) $this->option('reject')) {
            $this->error('Pilih salah satu --approve atau --reject.');

            return self::FAILURE;
        }
        $payment = Payment::findOrFail($this->argument('payment'));
        $this->table(['ID', 'Order', 'Nominal', 'Status'], [[$payment->id, $payment->order_id, $payment->gross_amount, $payment->status]]);
        $billing->review($payment, (bool) $this->option('approve'), (string) $this->option('note'));
        $this->info('Pembayaran telah diverifikasi.');

        return self::SUCCESS;
    }
}
