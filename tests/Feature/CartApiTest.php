<?php

namespace Tests\Feature;

use Tests\TestCase;

class CartApiTest extends TestCase
{
    public function test_cart_check_returns_consistent_payload_for_empty_ids(): void
    {
        $response = $this->postJson('/api/v2/cart/check', [
            'ids' => [],
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'cart' => [
                    'id',
                    'count',
                    'items',
                    'total',
                    'has_gift_voucher',
                    'gift_voucher_only',
                ],
                'message',
            ]);
    }

    public function test_cart_check_ignores_non_numeric_ids_and_still_returns_cart_payload(): void
    {
        $response = $this->postJson('/api/v2/cart/check', [
            'ids' => ['gift-voucher', 'abc'],
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'cart' => [
                    'id',
                    'count',
                    'items',
                    'total',
                    'has_gift_voucher',
                    'gift_voucher_only',
                ],
                'message',
            ]);
    }
}
