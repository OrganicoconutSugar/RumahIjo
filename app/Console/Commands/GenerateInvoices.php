<?php

namespace App\Console\Commands;

use App\Services\PortalActivity;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateInvoices extends Command
{
    private const ADVANCE_DAYS = 7;

    private const CATCH_UP_DAYS = 30;

    protected $signature = 'invoices:generate';

    protected $description = 'Terbitkan sewa aktif mulai tujuh hari sebelum jatuh tempo, termasuk yang terlewat hingga 30 hari terakhir, tanpa duplikat';

    public function handle(): int
    {
        $created = 0;
        $earliestDue = today()->subDays(self::CATCH_UP_DAYS);
        $latestDue = today()->addDays(self::ADVANCE_DAYS);
        $periods = [today()->subMonthNoOverflow()->startOfMonth(), today()->startOfMonth(), today()->addMonthNoOverflow()->startOfMonth()];

        foreach (DB::table('tenancies')->where('status', 'active')->whereDate('end_date', '>=', $earliestDue)->lazyById() as $tenancy) {
            $start = Carbon::parse($tenancy->start_date);
            $tenancyEnd = Carbon::parse($tenancy->end_date);
            foreach ($periods as $period) {
                $due = $period->copy()->day(min($tenancy->due_day, $period->daysInMonth));
                $end = $period->copy()->endOfMonth();
                if ($due->lt($start) || $due->gt($tenancyEnd) || $due->lt($earliestDue) || $due->gt($latestDue) || $end->lt($start) || $period->gt($tenancyEnd)) {
                    continue;
                }
                DB::transaction(function () use ($tenancy, $period, $end, $due, $tenancyEnd, &$created) {
                    $number = 'RIJ-'.$tenancy->id.'-'.$period->format('Ym');
                    if (! DB::table('invoices')->insertOrIgnore(['tenancy_id' => $tenancy->id, 'number' => $number, 'type' => 'rent', 'period_start' => $period->toDateString(), 'period_end' => $end->min($tenancyEnd)->toDateString(), 'amount' => $tenancy->monthly_price, 'due_date' => $due->toDateString(), 'status' => 'unpaid', 'created_at' => now(), 'updated_at' => now()])) {
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
