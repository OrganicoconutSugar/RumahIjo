<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TicketAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    private function ticketWithAttachment(User $user): array
    {
        Storage::fake('local');
        $ticket = Ticket::create(['user_id' => $user->id, 'subject' => 'Lampu', 'category' => 'Fasilitas', 'priority' => 'normal', 'status' => 'open']);
        $this->actingAs($user)->post('/penyewa/tiket/'.$ticket->id.'/pesan', ['body' => 'Foto', 'attachments' => [UploadedFile::fake()->image('lampu.png')]])->assertRedirect();

        return [$ticket, TicketMessage::where('ticket_id', $ticket->id)->firstOrFail()];
    }

    public function test_attachment_is_served_privately_through_a_signed_link(): void
    {
        $user = User::factory()->create(['role' => 'tenant'])->refresh();
        [$ticket, $message] = $this->ticketWithAttachment($user);

        $response = $this->get(URL::temporarySignedRoute('tenant.ticket.attachment', now()->addMinutes(5), ['ticket' => $ticket->id, 'message' => $message->id, 'index' => 0]));

        $response->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_attachment_rejects_unsigned_and_expired_links_with_403(): void
    {
        $user = User::factory()->create(['role' => 'tenant'])->refresh();
        [$ticket, $message] = $this->ticketWithAttachment($user);
        $parameters = ['ticket' => $ticket->id, 'message' => $message->id, 'index' => 0];

        $this->get(route('tenant.ticket.attachment', $parameters))->assertForbidden();
        $this->get(URL::temporarySignedRoute('tenant.ticket.attachment', now()->subMinute(), $parameters))->assertForbidden();
    }

    public function test_attachment_returns_404_for_unknown_index_or_message_of_another_ticket(): void
    {
        $user = User::factory()->create(['role' => 'tenant'])->refresh();
        [$ticket, $message] = $this->ticketWithAttachment($user);
        $otherTicket = Ticket::create(['user_id' => $user->id, 'subject' => 'Lain', 'category' => 'Lainnya', 'priority' => 'normal', 'status' => 'open']);

        $this->get(URL::temporarySignedRoute('tenant.ticket.attachment', now()->addMinutes(5), ['ticket' => $ticket->id, 'message' => $message->id, 'index' => 5]))->assertNotFound();
        $this->get(URL::temporarySignedRoute('tenant.ticket.attachment', now()->addMinutes(5), ['ticket' => $otherTicket->id, 'message' => $message->id, 'index' => 0]))->assertNotFound();
    }
}
