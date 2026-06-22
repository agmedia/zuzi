<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Shipping\BoxNowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BoxNowShipmentActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_index_marks_boxnow_order_with_existing_shipment_as_sent(): void
    {
        $user = User::factory()->create();
        $orderId = $this->createBoxNowOrder([
            'shipping_carrier' => BoxNowService::CARRIER,
            'shipping_parcel_id' => '6269041745',
            'tracking_code' => '6269041745',
            'shipping_tracking_status' => 'Čeka se preuzimanje iz e-trgovine',
            'printed' => false,
        ]);

        $response = $this->actingAs($user)->get(route('orders'));

        $response->assertOk();
        $response->assertSee('<i class="fa fa-fw fa-check text-success"></i>', false);
        $response->assertDontSee('sendBoxNow(' . $orderId . ')', false);
    }

    public function test_orders_index_keeps_boxnow_send_button_without_existing_shipment(): void
    {
        $user = User::factory()->create();
        $orderId = $this->createBoxNowOrder();

        $response = $this->actingAs($user)->get(route('orders'));

        $response->assertOk();
        $response->assertSee('sendBoxNow(' . $orderId . ')', false);
    }

    private function createBoxNowOrder(array $overrides = []): int
    {
        return (int) DB::table('orders')->insertGetId(array_merge([
            'user_id' => 0,
            'affiliate_id' => 0,
            'order_status_id' => (int) config('settings.order.status.paid'),
            'invoice' => null,
            'total' => 13.50,
            'payment_fname' => 'Roberta',
            'payment_lname' => 'Spajić',
            'payment_address' => 'Test ulica 1',
            'payment_zip' => '10000',
            'payment_city' => 'Zagreb',
            'payment_phone' => null,
            'payment_email' => 'boxnow@example.com',
            'payment_method' => 'Kartice',
            'payment_code' => 'corvus',
            'payment_card' => null,
            'payment_installment' => 0,
            'shipping_fname' => 'Roberta',
            'shipping_lname' => 'Spajić',
            'shipping_address' => 'Test ulica 1',
            'shipping_zip' => '10000',
            'shipping_city' => 'Zagreb',
            'shipping_phone' => null,
            'shipping_email' => 'boxnow@example.com',
            'shipping_method' => 'BoxNow',
            'shipping_code' => 'boxnow',
            'shipping_carrier' => null,
            'shipping_parcel_id' => null,
            'shipping_tracking_status' => null,
            'company' => '',
            'oib' => '',
            'comment' => null,
            'tracking_code' => '',
            'shipped' => false,
            'printed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
