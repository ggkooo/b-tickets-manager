<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        config(['app.api_key' => 'test-api-key']);

        $response = $this->get('/');

        $response->assertStatus(401);
    }
}
