<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        config(['app.api_key' => 'test-api-key']);

        $response = $this->get('/');

        $response->assertStatus(401);
    }
}
