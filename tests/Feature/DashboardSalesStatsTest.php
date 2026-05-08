<?php

namespace Tests\Feature;

use App\Models\Back\Orders\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardSalesStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_sales_chart_includes_processing_status_orders(): void
    {
        $this->createOrder((int) config('settings.order.status.processing'), 123.45, '2026-04-10 09:00:00');
        $this->createOrder((int) config('settings.order.status.canceled'), 10.00, '2026-04-10 10:00:00');
        $this->createOrder((int) config('settings.order.status.declined'), 20.00, '2026-04-10 11:00:00');
        $this->createOrder((int) config('settings.order.status.unfinished'), 30.00, '2026-04-10 12:00:00');

        $row = Order::query()
            ->selectRaw('DAY(created_at) as day, SUM(total) as total, COUNT(id) as orders')
            ->whereYear('created_at', 2026)
            ->whereMonth('created_at', 4)
            ->dashboardSales()
            ->groupBy('day')
            ->first();

        $this->assertSame(10, (int) $row->day);
        $this->assertSame(1, (int) $row->orders);
        $this->assertSame(123.45, round((float) $row->total, 2));
    }

    private function createOrder(int $statusId, float $total, string $createdAt): void
    {
        DB::table('orders')->insert([
            'user_id' => 0,
            'affiliate_id' => 0,
            'order_status_id' => $statusId,
            'invoice' => null,
            'total' => $total,
            'payment_fname' => 'Test',
            'payment_lname' => 'Kupac',
            'payment_address' => 'Test ulica 1',
            'payment_zip' => '10000',
            'payment_city' => 'Zagreb',
            'payment_phone' => null,
            'payment_email' => 'test@example.com',
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
            'shipping_email' => 'test@example.com',
            'shipping_method' => 'Dostava',
            'shipping_code' => 'gls',
            'company' => '',
            'oib' => '',
            'comment' => null,
            'tracking_code' => '',
            'shipped' => false,
            'printed' => false,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
