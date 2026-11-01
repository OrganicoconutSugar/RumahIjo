<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_owner_can_open_the_dashboard(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);

        $this->actingAs($owner)->get('/pemilik/dashboard')->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/pemilik/dashboard')->assertRedirect('/login');
    }

    public function test_tenant_is_forbidden_with_403(): void
    {
        $tenant = User::factory()->create(['role' => 'tenant']);

        $this->actingAs($tenant)->get('/pemilik/dashboard')->assertForbidden();
    }

    public function test_inactive_owner_is_forbidden_with_403(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => false]);

        $this->actingAs($owner)->get('/pemilik/dashboard')->assertForbidden();
    }
}
