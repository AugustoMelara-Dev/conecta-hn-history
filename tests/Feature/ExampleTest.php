<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * A basic test example - tests that application boots and responds.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // The landing page uses Livewire and may require database
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
