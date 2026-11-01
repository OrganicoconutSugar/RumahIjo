<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PortalFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_pages_require_an_active_tenant(): void
    {
        $this->get('/penyewa/kamar')->assertRedirect('/login');
        $owner = User::factory()->create(['role' => 'owner'])->refresh();
        $this->actingAs($owner)->get('/penyewa/biodata')->assertForbidden();
        $tenant = User::factory()->create(['role' => 'tenant'])->refresh();
        foreach (['dashboard', 'kalender', 'kamar', 'biodata', 'pembayaran', 'tiket', 'pengaturan'] as $page) {
            $this->actingAs($tenant)->get('/penyewa/'.$page)->assertOk();
        }
    }

    public function test_biodata_validates_and_encrypts_nik(): void
    {
        $user = User::factory()->create(['role' => 'tenant'])->refresh();
        $this->actingAs($user)->put('/penyewa/biodata', [])->assertSessionHasErrors(['full_name', 'consent']);
        $data = ['full_name' => 'Budi', 'phone' => '08123456789', 'gender' => 'male', 'birth_place' => 'Bandung', 'birth_date' => '1999-01-01', 'nik' => '3201000000000001', 'occupation' => 'Mahasiswa', 'origin_address' => 'Bandung', 'emergency_name' => 'Ibu', 'emergency_relation' => 'Ibu', 'emergency_phone' => '08123', 'consent' => true];
        $this->actingAs($user)->put('/penyewa/biodata', $data)->assertRedirect();
        $saved = DB::table('tenant_profiles')->where('user_id', $user->id)->first();
        $this->assertNotSame($data['nik'], $saved->nik);
        $this->assertSame($data['nik'], Crypt::decryptString($saved->nik));
        $this->actingAs($user)->get('/penyewa/biodata')->assertOk()->assertDontSee($data['nik']);
    }

    public function test_tickets_are_scoped_to_owner_and_messages_are_saved(): void
    {
        $one = User::factory()->create(['role' => 'tenant'])->refresh();
        $two = User::factory()->create(['role' => 'tenant'])->refresh();
        $this->actingAs($one)->post('/penyewa/tiket', ['subject' => 'Lampu', 'category' => 'Fasilitas', 'priority' => 'normal', 'body' => 'Lampu mati'])->assertRedirect();
        $id = DB::table('tickets')->value('id');
        $this->actingAs($two)->get('/penyewa/tiket/'.$id)->assertNotFound();
        $this->actingAs($two)->post('/penyewa/tiket/'.$id.'/pesan', ['body' => 'Tidak boleh'])->assertNotFound();
        $this->actingAs($one)->post('/penyewa/tiket/'.$id.'/pesan', ['body' => 'Mohon bantu'])->assertRedirect();
        $this->assertDatabaseHas('ticket_messages', ['ticket_id' => $id, 'body' => 'Mohon bantu', 'sender_id' => $one->id]);
        $this->actingAs($one)->patch('/penyewa/tiket/'.$id.'/status', ['status' => 'closed'])->assertRedirect();
        $this->actingAs($one)->post('/penyewa/tiket/'.$id.'/pesan', ['body' => 'Tertutup'])->assertStatus(422);
    }
}
