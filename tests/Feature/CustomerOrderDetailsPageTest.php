<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserDetail;
use App\Services\Shipping\BoxNowService;
use App\Services\Shipping\GlsTrackingService;
use App\Services\Shipping\OrderTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerOrderDetailsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_orders_link_to_dedicated_order_page(): void
    {
        $user = $this->createCustomer('customer@example.com');
        $orderId = $this->createOrderFor($user);

        $this->actingAs($user)
            ->get(route('moje-narudzbe'))
            ->assertOk()
            ->assertSee(route('moje-narudzbe.show', ['order' => $orderId]), false)
            ->assertDontSee('data-bs-toggle="modal"', false)
            ->assertDontSee('#order-details', false);
    }

    public function test_customer_can_view_dedicated_order_page_with_refresh_button(): void
    {
        $user = $this->createCustomer('customer@example.com');
        $orderId = $this->createOrderFor($user, [
            'shipping_carrier' => GlsTrackingService::CARRIER,
            'tracking_code' => '123456789',
            'shipping_tracking_status' => 'U dostavi',
            'shipping_tracking_url' => 'https://gls.example.test/track/123456789',
        ]);
        $this->createOrderProduct($orderId);
        $this->createOrderTotal($orderId);

        $this->actingAs($user)
            ->get(route('moje-narudzbe.show', ['order' => $orderId]))
            ->assertOk()
            ->assertSee('Narudžba #' . $orderId)
            ->assertSee('Povratak na sve narudžbe')
            ->assertSee('Test knjiga')
            ->assertSee('U dostavi')
            ->assertSee('Osvježi status')
            ->assertSee(route('moje-narudzbe.tracking.refresh', ['order' => $orderId]), false);
    }

    public function test_customer_order_page_hides_tracking_section_until_parcel_exists(): void
    {
        $user = $this->createCustomer('customer@example.com');
        $orderId = $this->createOrderFor($user, [
            'shipping_carrier' => BoxNowService::CARRIER,
            'shipping_method' => 'BoxNow',
            'shipping_code' => 'boxnow',
            'tracking_code' => '',
            'shipping_parcel_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('moje-narudzbe.show', ['order' => $orderId]))
            ->assertOk()
            ->assertSee('Narudžba #' . $orderId)
            ->assertDontSee('<div class="account-order-meta-label">Broj pošiljke</div>', false)
            ->assertDontSee('Osvježi status')
            ->assertDontSee(route('moje-narudzbe.tracking.refresh', ['order' => $orderId]), false);
    }

    public function test_boxnow_tracking_button_prefills_parcel_number(): void
    {
        config(['services.boxnow.tracking_url' => 'https://track.boxnow.hr/en/track']);

        $user = $this->createCustomer('customer@example.com');
        $orderId = $this->createOrderFor($user, [
            'shipping_carrier' => BoxNowService::CARRIER,
            'shipping_method' => 'BoxNow',
            'shipping_code' => 'boxnow',
            'tracking_code' => '5936763647',
            'shipping_tracking_status' => 'Čeka se preuzimanje iz e-trgovine',
            'shipping_tracking_url' => 'https://track.boxnow.hr/en/track',
        ]);

        $this->actingAs($user)
            ->get(route('moje-narudzbe.show', ['order' => $orderId]))
            ->assertOk()
            ->assertSee('href="https://track.boxnow.hr/en?track=5936763647"', false);
    }

    public function test_customer_cannot_view_another_customers_order_page(): void
    {
        $user = $this->createCustomer('customer@example.com');
        $otherUser = $this->createCustomer('other@example.com');
        $orderId = $this->createOrderFor($otherUser);

        $this->actingAs($user)
            ->get(route('moje-narudzbe.show', ['order' => $orderId]))
            ->assertNotFound();
    }

    public function test_customer_can_refresh_tracking_for_own_order(): void
    {
        $user = $this->createCustomer('customer@example.com');
        $orderId = $this->createOrderFor($user, [
            'shipping_carrier' => GlsTrackingService::CARRIER,
            'tracking_code' => '123456789',
        ]);

        $this->mock(OrderTrackingService::class, function ($mock) {
            $mock->shouldReceive('resolveCarrier')
                ->once()
                ->andReturn(GlsTrackingService::CARRIER);
            $mock->shouldReceive('refresh')
                ->once()
                ->andReturn([
                    'updated' => true,
                    'message' => 'Tracking je osvježen: U dostavi',
                    'tracking' => [],
                ]);
        });

        $this->actingAs($user)
            ->post(route('moje-narudzbe.tracking.refresh', ['order' => $orderId]))
            ->assertRedirect(route('moje-narudzbe.show', ['order' => $orderId]))
            ->assertSessionHas('success', 'Tracking je osvježen: U dostavi');
    }

    public function test_customer_cannot_refresh_another_customers_order_tracking(): void
    {
        $user = $this->createCustomer('customer@example.com');
        $otherUser = $this->createCustomer('other@example.com');
        $orderId = $this->createOrderFor($otherUser, [
            'shipping_carrier' => GlsTrackingService::CARRIER,
            'tracking_code' => '123456789',
        ]);

        $this->mock(OrderTrackingService::class, function ($mock) {
            $mock->shouldNotReceive('refresh');
        });

        $this->actingAs($user)
            ->post(route('moje-narudzbe.tracking.refresh', ['order' => $orderId]))
            ->assertNotFound();
    }

    private function createCustomer(string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
        ]);

        UserDetail::create([
            'user_id' => $user->id,
            'fname' => 'Test',
            'lname' => 'Kupac',
            'role' => 'customer',
        ]);

        return $user;
    }

    private function createOrderFor(User $user, array $overrides = []): int
    {
        return DB::table('orders')->insertGetId(array_merge([
            'user_id' => $user->id,
            'affiliate_id' => 0,
            'order_status_id' => (int) config('settings.order.status.paid', 3),
            'invoice' => null,
            'total' => 13.27,
            'payment_fname' => 'Test',
            'payment_lname' => 'Kupac',
            'payment_address' => 'Test ulica 1',
            'payment_zip' => '10000',
            'payment_city' => 'Zagreb',
            'payment_phone' => null,
            'payment_email' => $user->email,
            'payment_method' => 'Kartice',
            'payment_code' => 'corvus',
            'payment_card' => null,
            'payment_installment' => 0,
            'shipping_fname' => 'Test',
            'shipping_lname' => 'Kupac',
            'shipping_address' => 'Test ulica 1',
            'shipping_zip' => '10000',
            'shipping_city' => 'Zagreb',
            'shipping_phone' => null,
            'shipping_email' => $user->email,
            'shipping_method' => 'GLS dostava',
            'shipping_code' => 'gls',
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

    private function createOrderProduct(int $orderId): void
    {
        DB::table('order_products')->insert([
            'order_id' => $orderId,
            'product_id' => 0,
            'name' => 'Test knjiga',
            'quantity' => 1,
            'org_price' => 13.27,
            'discount' => null,
            'price' => 13.27,
            'total' => 13.27,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrderTotal(int $orderId): void
    {
        DB::table('order_total')->insert([
            'order_id' => $orderId,
            'code' => 'total',
            'title' => 'Ukupno',
            'value' => 13.27,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
