<?php

namespace App\Console\Commands;

use App\Services\PortalActivity;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateInvoices extends Command
{
    protected $signature = 'invoices:generate';

    protected $description = 'Terbitkan sewa aktif mulai tujuh hari sebelum jatuh tempo tanpa duplikat';

    public function handle(): int
    {
        $created = 0;
        foreach (DB::table('tenancies')->where('status', 'active')->whereDate('end_date', '>=', today())->get() as $tenancy) {
            foreach ([today()->startOfMonth(), today()->addMonthNoOverflow()->startOfMonth()] as $period) {
                $due = $period->copy()->day($tenancy->due_day);
                $end = $period->copy()->endOfMonth();
                if ($due->lt(Carbon::parse($tenancy->start_date)) || $due->gt(Carbon::parse($tenancy->end_date)) || $due->lt(today()) || $due->gt(today()->addDays(7)) || $end->lt(Carbon::parse($tenancy->start_date)) || $period->gt(Carbon::parse($tenancy->end_date))) {
                    continue;
                }
                DB::transaction(function () use ($tenancy, $period, $end, $due, &$created) {
                    $number = 'RIJ-'.$tenancy->id.'-'.$period->format('Ym');
                    if (! DB::table('invoices')->insertOrIgnore(['tenancy_id' => $tenancy->id, 'number' => $number, 'type' => 'rent', 'period_start' => $period->toDateString(), 'period_end' => $end->min(Carbon::parse($tenancy->end_date))->toDateString(), 'amount' => $tenancy->monthly_price, 'due_date' => $due->toDateString(), 'status' => 'unpaid', 'created_at' => now(), 'updated_at' => now()])) {
                        return;
                    }
                    $created++;
                    $id = DB::table('invoices')->where('number', $number)->value('id');
                    PortalActivity::notify($tenancy->user_id, 'invoice-new-'.$id, 'Tagihan baru', 'Tagihan '.$number.' jatuh tempo '.$due->format('d-m-Y').'.', '/penyewa/pembayaran/'.$id);
                });
            }
        }
        $this->info($created.' tagihan diterbitkan.');

        return self::SUCCESS;
    }
}
