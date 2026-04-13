<?php

namespace Tests\Feature;

use Tests\TestCase;

class SettingsApiTest extends TestCase
{
    public function test_settings_endpoint_returns_frontend_fallback_shape(): void
    {
        $response = $this->getJson('/api/v2/settings/get');

        $response->assertOk();

        $data = $response->json();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('currency.list', $data);
        $this->assertIsArray($data['currency.list']);
        $this->assertNotEmpty($data['currency.list']);
    }
}
