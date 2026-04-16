<?php

namespace Tests\Unit;

use App\Helpers\Currency;
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

    public function test_front_api_defaults_only_expose_active_currencies(): void
    {
        $defaults = Settings::frontApiDefaults();

        $this->assertSame(['EUR'], array_column($defaults['currency.list'], 'code'));
    }

    public function test_normalize_front_api_payload_filters_inactive_currencies(): void
    {
        $payload = [
            'currency.list' => [
                [
                    'code' => 'HRK',
                    'main' => false,
                    'status' => false,
                ],
                [
                    'code' => 'EUR',
                    'main' => true,
                    'status' => true,
                ],
            ],
            'payment.list' => [
                ['code' => 'cod'],
            ],
        ];

        $normalized = Settings::normalizeFrontApiPayload($payload);

        $this->assertSame(['EUR'], array_column($normalized['currency.list'], 'code'));
        $this->assertSame($payload['payment.list'], $normalized['payment.list']);
    }

    public function test_currency_helper_does_not_resolve_inactive_secondary_currency(): void
    {
        $this->assertFalse(Currency::secondary());
        $this->assertSame('10,00 €', Currency::main(10, true));
    }
}
