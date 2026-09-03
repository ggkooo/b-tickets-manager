<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TicketServiceTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_it_accepts_unified_exam_sample_service_type(): void
    {
        config(['app.api_key' => 'test-api-key']);

        $response = $this
            ->withHeader('X-API-KEY', 'test-api-key')
            ->postJson('/api/tickets', [
                'service_type' => 'Retirada de Exames ou Entrega de Amostras',
                'location' => 'campus',
            ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('ticket.service_type', 'Retirada de Exames ou Entrega de Amostras')
            ->assertJsonPath('ticket.completed', false);

        $this->assertStringStartsWith('E-', $response->json('ticket.key'));
    }

    public static function creServiceTypeProvider(): array
    {
        return [
            'academico' => ['Acadêmico/Matrículas', 'A'],
            'documentos' => ['Solicitação de Documentos', 'D'],
            'boletos' => ['Impressão de Boletos', 'B'],
            'financiamentos' => ['Financiamentos e Bolsas', 'F'],
            'renegociacao' => ['Renegociação de Mensalidades', 'R'],
        ];
    }

    #[DataProvider('creServiceTypeProvider')]
    public function test_it_accepts_cre_service_types(string $serviceType, string $expectedPrefix): void
    {
        config(['app.api_key' => 'test-api-key']);

        $response = $this
            ->withHeader('X-API-KEY', 'test-api-key')
            ->postJson('/api/tickets', [
                'service_type' => $serviceType,
                'location' => 'ijui',
            ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('ticket.service_type', $serviceType)
            ->assertJsonPath('ticket.location', 'ijui')
            ->assertJsonPath('ticket.completed', false);

        $this->assertStringStartsWith($expectedPrefix . '-', $response->json('ticket.key'));
    }

    public function test_it_rejects_unilab_service_type_at_a_cre_location(): void
    {
        config(['app.api_key' => 'test-api-key']);

        $response = $this
            ->withHeader('X-API-KEY', 'test-api-key')
            ->postJson('/api/tickets', [
                'service_type' => 'Atendimento Normal',
                'location' => 'ijui',
            ]);

        $response->assertStatus(422);
    }

    public function test_it_rejects_cre_service_type_at_a_unilab_location(): void
    {
        config(['app.api_key' => 'test-api-key']);

        $response = $this
            ->withHeader('X-API-KEY', 'test-api-key')
            ->postJson('/api/tickets', [
                'service_type' => 'Acadêmico/Matrículas',
                'location' => 'campus',
            ]);

        $response->assertStatus(422);
    }
}
