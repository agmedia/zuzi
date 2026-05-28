<?php

namespace Tests\Feature;

use App\Helpers\Helper;
use App\Models\Back\Marketing\Action;
use App\Models\Back\Orders\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderCompletedWithoutPromoMailFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_filter_returns_completed_orders_without_sent_promo_mail(): void
    {
        $this->seedOrderStatuses();

        $completedWithoutMailId = $this->createOrder(9, 'bez-maila@example.com');
        $completedWithMailId = $this->createOrder(9, 'poslan@example.com');
        $completedWithCouponId = $this->createOrder(9, 'kupon@example.com');
        $completedWithoutEmailId = $this->createOrder(9, '');
        $newWithoutMailId = $this->createOrder(1, 'nova@example.com');

        $this->createSentPromoAction($completedWithMailId);
        $this->addOrderTotal($completedWithCouponId, 'special', 'Kupon HVALA20-POSTOJI', -4.00);

        $filteredIds = (new Order())
            ->filter(new Request(['completed_without_promo_mail' => 1]))
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([$completedWithoutMailId], $filteredIds);
        $this->assertNotContains($completedWithMailId, $filteredIds);
        $this->assertNotContains($completedWithCouponId, $filteredIds);
        $this->assertNotContains($completedWithoutEmailId, $filteredIds);
        $this->assertNotContains($newWithoutMailId, $filteredIds);
    }

    public function test_completed_without_promo_mail_filter_overrides_stale_status_query(): void
    {
        $this->seedOrderStatuses();

        $completedWithoutMailId = $this->createOrder(9, 'bez-maila@example.com');
        $this->createOrder(1, 'nova@example.com');

        $filteredIds = (new Order())
            ->filter(new Request([
                'completed_without_promo_mail' => 1,
                'status' => 1,
            ]))
            ->pluck('id')
            ->all();

        $this->assertSame([$completedWithoutMailId], $filteredIds);
    }

    private function seedOrderStatuses(): void
    {
        DB::table('settings')->insert([
            'code' => 'order',
            'key' => 'statuses',
            'value' => json_encode([
                ['id' => 1, 'title' => 'Novo', 'color' => 'info', 'sort_order' => 1],
                ['id' => 9, 'title' => 'Završeno', 'color' => 'primary', 'sort_order' => 2],
            ]),
            'json' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Helper::flushCache('settings', 'orderstatuses');
    }

    private function createSentPromoAction(int $orderId): void
    {
        Action::query()->create([
            'title' => 'Promo za nedovrsenu narudzbu #' . $orderId,
            'type' => 'P',
            'discount' => 20,
            'group' => 'total',
            'links' => json_encode(['total']),
            'date_start' => now()->subDay(),
            'date_end' => now()->addDay(),
            'coupon' => 'HVALA20-TEST123',
            'quantity' => 1,
            'status' => 1,
        ]);
    }

    private function createOrder(int $statusId, string $email): int
    {
        return (int) DB::table('orders')->insertGetId([
            'user_id' => 0,
            'affiliate_id' => 0,
            'order_status_id' => $statusId,
            'invoice' => null,
            'total' => 25,
            'payment_fname' => 'Ana',
            'payment_lname' => 'Anić',
            'payment_address' => 'Test ulica 1',
            'payment_zip' => '10000',
            'payment_city' => 'Zagreb',
            'payment_phone' => null,
            'payment_email' => $email,
            'payment_method' => 'Kartice',
            'payment_code' => 'corvus',
            'payment_card' => null,
            'payment_installment' => 0,
            'shipping_fname' => 'Ana',
            'shipping_lname' => 'Anić',
            'shipping_address' => 'Test ulica 1',
            'shipping_zip' => '10000',
            'shipping_city' => 'Zagreb',
            'shipping_phone' => null,
            'shipping_email' => $email,
            'shipping_method' => 'Dostava',
            'shipping_code' => 'gls',
            'company' => '',
            'oib' => '',
            'comment' => null,
            'tracking_code' => '',
            'shipped' => false,
            'printed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function addOrderTotal(int $orderId, string $code, string $title, float $value): void
    {
        DB::table('order_total')->insert([
            'order_id' => $orderId,
            'code' => $code,
            'title' => $title,
            'value' => $value,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
