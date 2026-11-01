<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PortalActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PortalActivityTest extends TestCase
{
    use RefreshDatabase;

    private function sentEmails(): int
    {
        return count(app('mail.manager')->mailer()->getSymfonyTransport()->messages());
    }

    public function test_email_is_not_sent_when_the_surrounding_transaction_rolls_back(): void
    {
        $user = User::factory()->create(['role' => 'tenant'])->refresh();

        try {
            DB::transaction(function () use ($user) {
                PortalActivity::notify($user->id, 'rolled-back', 'Tagihan', 'Pesan', '/penyewa/pembayaran');
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, $this->sentEmails());
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('notification_deliveries', 0);
    }

    public function test_email_is_sent_once_the_surrounding_transaction_commits(): void
    {
        $user = User::factory()->create(['role' => 'tenant'])->refresh();

        DB::transaction(fn () => PortalActivity::notify($user->id, 'committed', 'Tagihan', 'Pesan', '/penyewa/pembayaran'));

        $this->assertSame(1, $this->sentEmails());
        $this->assertDatabaseCount('notifications', 1);
    }
}
