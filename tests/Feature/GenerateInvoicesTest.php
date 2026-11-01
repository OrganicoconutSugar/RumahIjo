<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GenerateInvoicesTest extends TestCase
{
    use RefreshDatabase;

    private function tenancy(array $attributes = []): int
    {
        $user = User::factory()->create(['role' => 'tenant']);
        $type = DB::table('room_types')->insertGetId(['name' => 'Standar', 'price_monthly' => 850000]);
        $room = DB::table('rooms')->insertGetId(['room_type_id' => $type, 'number' => '101', 'floor' => 1, 'status' => 'occupied']);

        return DB::table('tenancies')->insertGetId(['user_id' => $user->id, 'room_id' => $room, 'start_date' => '2026-10-01', 'end_date' => '2027-12-31', 'due_day' => 5, 'monthly_price' => 850000, 'deposit_amount' => 0, 'status' => 'active', ...$attributes]);
    }

    public function test_issues_invoice_missed_while_scheduler_was_down(): void
    {
        Notification::fake();
        $this->travelTo('2026-11-12 06:00:00');
        $tenancy = $this->tenancy();

        $this->artisan('invoices:generate')->assertSuccessful();

        $this->assertDatabaseHas('invoices', ['tenancy_id' => $tenancy, 'period_start' => '2026-11-01', 'due_date' => '2026-11-05', 'status' => 'unpaid', 'amount' => 850000]);
        $this->assertDatabaseMissing('invoices', ['period_start' => '2026-10-01']);
        $this->assertDatabaseMissing('invoices', ['period_start' => '2026-12-01']);
    }

    public function test_does_not_backfill_periods_older_than_the_catch_up_window(): void
    {
        Notification::fake();
        $this->travelTo('2026-11-12 06:00:00');
        $this->tenancy(['start_date' => '2026-01-01']);

        $this->artisan('invoices:generate')->assertSuccessful();

        $this->assertSame(['2026-11-01'], DB::table('invoices')->pluck('period_start')->all());
    }

    public function test_clamps_due_day_to_last_day_of_a_short_month(): void
    {
        Notification::fake();
        $this->travelTo('2026-11-25 06:00:00');
        $this->tenancy(['start_date' => '2026-11-01', 'due_day' => 31]);

        $this->artisan('invoices:generate')->assertSuccessful();

        $this->assertSame(['2026-11-30'], DB::table('invoices')->pluck('due_date')->all());
    }

    public function test_skips_tenancies_that_are_not_active(): void
    {
        Notification::fake();
        $this->travelTo('2026-11-02 06:00:00');
        $this->tenancy(['status' => 'pending']);

        $this->artisan('invoices:generate')->assertSuccessful();

        $this->assertDatabaseCount('invoices', 0);
    }
}
