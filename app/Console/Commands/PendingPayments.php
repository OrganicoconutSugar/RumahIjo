<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;

class PendingPayments extends Command
{
    protected $signature = 'payments:pending';

    protected $description = 'Daftar bukti QRIS yang menunggu dicocokkan dengan dana merchant';

    public function handle(): int
    {
        $rows = Payment::with('invoice')->where('status', 'pending')->where('gateway', 'qris_static')->get()->map(fn ($payment) => [$payment->id, $payment->invoice->number, $payment->gross_amount, $payment->transaction_id, storage_path('app/private/'.$payment->getRawOriginal('proof_path'))])->all();
        $this->table(['ID', 'Tagihan', 'Nominal', 'Referensi', 'Bukti privat lokal'], $rows);

        return self::SUCCESS;
    }
}
