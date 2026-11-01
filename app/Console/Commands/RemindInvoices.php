<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\PortalActivity;
use Illuminate\Console\Command;

class RemindInvoices extends Command
{
    protected $signature = 'invoices:remind';

    protected $description = 'Kirim pengingat tiga hari sebelum jatuh tempo';

    public function handle(): int
    {
        foreach (Invoice::where('status', 'unpaid')->whereDate('due_date', today()->addDays(3))->get() as $invoice) {
            PortalActivity::notify($invoice->tenantId(), 'invoice-reminder-'.$invoice->id.'-'.$invoice->due_date->toDateString(), 'Pengingat tagihan', 'Tagihan '.$invoice->number.' jatuh tempo tiga hari lagi.', '/penyewa/pembayaran/'.$invoice->id);
        }

        return self::SUCCESS;
    }
}
