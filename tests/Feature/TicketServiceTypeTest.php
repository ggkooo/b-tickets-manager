<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketServiceTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_accepts_unified_exam_sample_service_type(): void
    {
        config(['app.api_key' => 'test-api-key']);

        $response = $this
            ->withHeader('X-API-KEY', 'test-api-key')
            ->postJson('/api/tickets', [
                'service_type' => 'Recebimento de Exames ou Entrega de Amostras',
            ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('ticket.service_type', 'Recebimento de Exames ou Entrega de Amostras')
            ->assertJsonPath('ticket.completed', false);

        $this->assertStringStartsWith('E-', $response->json('ticket.key'));
    }
}
