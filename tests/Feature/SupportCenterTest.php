<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\User;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportCenterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test retrieving FAQs.
     */
    public function test_can_retrieve_faqs()
    {
        Faq::create([
            'question' => 'How do I rent a home?',
            'answer' => 'Browse listings and select Book Tour.',
            'category' => 'general'
        ]);

        $response = $this->getJson('/api/faqs');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         '*' => ['id', 'question', 'answer', 'category', 'created_at', 'updated_at']
                     ]
                 ]);
    }

    /**
     * Test submitting a contact form message.
     */
    public function test_can_submit_contact_message()
    {
        $response = $this->postJson('/api/contact', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'Listing Question',
            'message' => 'Hello, I have a question about the fees.'
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Your message has been sent successfully. We will get back to you shortly.'
                 ]);

        $this->assertDatabaseHas('contact_messages', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'unread'
        ]);
    }

    /**
     * Test contact form validation constraints.
     */
    public function test_contact_form_validates_inputs()
    {
        $response = $this->postJson('/api/contact', [
            'name' => '',
            'email' => 'not-an-email',
            'subject' => 'Hi',
            'message' => ''
        ]);

        $response->assertStatus(422)
                 ->assertJsonStructure(['success', 'errors']);
    }

    /**
     * Test unauthenticated ticket creation fails.
     */
    public function test_unauthenticated_user_cannot_create_ticket()
    {
        $response = $this->postJson('/api/tickets', [
            'subject' => 'Listing issue',
            'description' => 'I cannot upload photos.',
            'category' => 'listings'
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test authenticated ticket workflows.
     */
    public function test_authenticated_user_can_create_and_manage_ticket()
    {
        $user = User::factory()->create([
            'role' => 'tenant',
            'status' => 'active'
        ]);

        Sanctum::actingAs($user);

        // 1. Create Ticket
        $response = $this->postJson('/api/tickets', [
            'subject' => 'Listing issue',
            'description' => 'I cannot upload photos.',
            'category' => 'listings',
            'priority' => 'high'
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Support ticket created successfully.'
                 ]);

        $ticket = SupportTicket::first();
        $this->assertNotNull($ticket);
        $this->assertEquals($user->id, $ticket->user_id);
        $this->assertEquals('listings', $ticket->category);

        // 2. Fetch Ticket Details
        $detailResponse = $this->getJson("/api/tickets/{$ticket->id}");
        $detailResponse->assertStatus(200)
                       ->assertJsonPath('data.ticket.subject', 'Listing issue');

        // 3. Reply to Ticket
        $replyResponse = $this->postJson("/api/tickets/{$ticket->id}/reply", [
            'message' => 'Also it shows error code 500.'
        ]);

        $replyResponse->assertStatus(201);
        $this->assertDatabaseHas('support_ticket_replies', [
            'support_ticket_id' => $ticket->id,
            'message' => 'Also it shows error code 500.'
        ]);

        // 4. Close Ticket
        $closeResponse = $this->postJson("/api/tickets/{$ticket->id}/close");
        $closeResponse->assertStatus(200);
        $this->assertEquals('closed', $ticket->fresh()->status);
    }

    /**
     * Test admin ticketing capabilities.
     */
    public function test_admin_can_manage_all_tickets()
    {
        $user = User::factory()->create(['role' => 'tenant']);
        $admin = User::factory()->create(['role' => 'admin']);

        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'ticket_number' => 'TIC-TEST1122',
            'subject' => 'Payment issue',
            'description' => 'Card was charged twice.',
            'category' => 'billing',
            'priority' => 'high',
            'status' => 'open'
        ]);

        // 1. Access admin ticket index (unauthorized)
        Sanctum::actingAs($user);
        $this->getJson('/api/admin/tickets')->assertStatus(403);

        // 2. Access admin ticket index (authorized)
        Sanctum::actingAs($admin);
        $adminIndex = $this->getJson('/api/admin/tickets');
        $adminIndex->assertStatus(200)
                   ->assertJsonCount(1, 'data');

        // 3. Update status to in_progress
        $statusResponse = $this->putJson("/api/admin/tickets/{$ticket->id}/status", [
            'status' => 'in_progress'
        ]);
        $statusResponse->assertStatus(200);
        $this->assertEquals('in_progress', $ticket->fresh()->status);

        // 4. Reply to ticket as admin
        $replyResponse = $this->postJson("/api/admin/tickets/{$ticket->id}/reply", [
            'message' => 'We have refunded the second transaction. Please check your bank statement in 3 days.'
        ]);
        $replyResponse->assertStatus(201);
        
        $this->assertDatabaseHas('support_ticket_replies', [
            'support_ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'message' => 'We have refunded the second transaction. Please check your bank statement in 3 days.'
        ]);
    }
}
