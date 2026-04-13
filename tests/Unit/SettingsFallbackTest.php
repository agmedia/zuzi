<?php

namespace Tests\Unit;

use App\Models\Back\Settings\Settings;
use Tests\TestCase;

class SettingsFallbackTest extends TestCase
{
    public function test_front_api_defaults_include_currency_and_payment_payloads(): void
    {
        $defaults = Settings::frontApiDefaults();

        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('currency.list', $defaults);
        $this->assertArrayHasKey('payment.list', $defaults);
        $this->assertNotEmpty($defaults['currency.list']);
        $this->assertIsArray($defaults['payment.list']);
    }
}
