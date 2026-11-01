<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }

    public function test_active_tenant_can_log_in_and_session_is_required(): void
    {
        $user = User::factory()->create(['email' => 'test@example.test', 'password' => Hash::make('Pass1234'), 'role' => 'tenant', 'is_active' => true]);
        $this->get('/penyewa/dashboard')->assertRedirect('/login');
        $this->post('/login', ['email' => $user->email, 'password' => 'Pass1234'])->assertRedirect('/penyewa/dashboard');
        $this->get('/penyewa/dashboard')->assertOk();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Pass1234'), 'is_active' => false]);
        $this->post('/login', ['email' => $user->email, 'password' => 'Pass1234'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_availability_does_not_expose_tenant_data(): void
    {
        DB::table('room_types')->insert(['name' => 'Standar', 'price_monthly' => 850000]);
        DB::table('rooms')->insert(['room_type_id' => 1, 'number' => '101', 'floor' => 1, 'status' => 'occupied']);
        $this->getJson('/api/kamar/ketersediaan')->assertExactJson([['number' => '101', 'status' => 'occupied']]);
    }
}
