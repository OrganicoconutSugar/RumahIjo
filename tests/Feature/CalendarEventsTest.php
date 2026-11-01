<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CalendarEventsTest extends TestCase
{
    use RefreshDatabase;

    private function agenda(): array
    {
        return ['title' => 'Pertemuan penghuni', 'category' => 'meeting', 'starts_at' => '2026-11-01T09:00', 'ends_at' => '2026-11-01T10:00', 'location' => 'Ruang bersama', 'notes' => 'Bawa catatan', 'status' => 'planned'];
    }

    public function test_agenda_can_be_created_edited_and_deleted_only_by_its_tenant(): void
    {
        $one = User::factory()->create(['role' => 'tenant'])->refresh();
        $two = User::factory()->create(['role' => 'tenant'])->refresh();
        $this->actingAs($one)->post('/penyewa/agenda', [...$this->agenda(), 'user_id' => $two->id])->assertRedirect();
        $id = DB::table('calendar_events')->value('id');
        $this->assertDatabaseHas('calendar_events', ['id' => $id, 'user_id' => $one->id, 'title' => 'Pertemuan penghuni']);
        $this->actingAs($two)->put('/penyewa/agenda/'.$id, $this->agenda())->assertNotFound();
        $this->delete('/penyewa/agenda/'.$id)->assertNotFound();
        $this->get('/penyewa/kalender')->assertInertia(fn ($page) => $page->has('events', 0));
        $this->actingAs($one)->put('/penyewa/agenda/'.$id, [...$this->agenda(), 'title' => 'Agenda diperbarui'])->assertRedirect();
        $this->assertDatabaseHas('calendar_events', ['id' => $id, 'title' => 'Agenda diperbarui']);
        $this->get('/penyewa/kalender')->assertInertia(fn ($page) => $page->has('events', 1));
        $this->delete('/penyewa/agenda/'.$id)->assertRedirect();
        $this->assertDatabaseMissing('calendar_events', ['id' => $id]);
    }

    public function test_agenda_rejects_invalid_times_and_requires_login(): void
    {
        $this->post('/penyewa/agenda', $this->agenda())->assertRedirect('/login');
        $user = User::factory()->create(['role' => 'tenant'])->refresh();
        $this->actingAs($user)->post('/penyewa/agenda', [...$this->agenda(), 'ends_at' => '2026-11-01T08:00'])->assertSessionHasErrors('ends_at');
        $this->post('/penyewa/agenda', [])->assertSessionHasErrors(['title', 'starts_at', 'ends_at']);
        $this->assertDatabaseCount('calendar_events',0);
    }
}
