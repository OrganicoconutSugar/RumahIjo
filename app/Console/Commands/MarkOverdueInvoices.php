<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\PortalActivity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarkOverdueInvoices extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Tandai tagihan belum dibayar yang melewati jatuh tempo';

    public function handle(): int
    {
        foreach (Invoice::where('status', 'unpaid')->whereDate('due_date', '<', today())->get() as $invoice) {
            DB::transaction(function () use ($invoice) {
                $item = Invoice::whereKey($invoice->id)->lockForUpdate()->first();
                if ($item->status !== 'unpaid') {
                    return;
                }$item->status = 'overdue';
                $item->save();
                PortalActivity::notify($item->tenantId(), 'invoice-overdue-'.$item->id, 'Tagihan terlambat', 'Tagihan '.$item->number.' sudah melewati jatuh tempo.', '/penyewa/pembayaran/'.$item->id);
            });
        }

        return self::SUCCESS;
    }
}
