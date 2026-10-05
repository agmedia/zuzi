<?php

namespace Tests\Unit;

use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Catalog\Publisher;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderProduct;
use Tests\TestCase;

class OrderEmailDeliveryNoticeTest extends TestCase
{
    /**
     * @dataProvider publishersWithTwentyDayDelivery
     */
    public function test_order_email_shows_the_notice_below_matching_products(string $slug, string $title): void
    {
        $html = $this->renderOrderTable($slug, $title, true);

        $this->assertStringContainsString('Testna knjiga', $html);
        $this->assertStringContainsString('Dostupno u roku 20 dana.', $html);
        $this->assertLessThan(
            strpos($html, 'Dostupno u roku 20 dana.'),
            strpos($html, 'Testna knjiga')
        );
    }

    public function publishersWithTwentyDayDelivery(): array
    {
        return [
            'Delfi' => ['delfi', 'Delfi'],
            'Laguna' => ['laguna', 'Laguna'],
            'Laguna d.o.o.' => ['laguna-doo', 'Laguna d.o.o.'],
        ];
    }

    public function test_order_email_does_not_show_the_notice_for_other_publishers(): void
    {
        $html = $this->renderOrderTable('delfin', 'DELFIN', true);

        $this->assertStringNotContainsString('Dostupno u roku 20 dana.', $html);
    }

    public function test_later_status_emails_can_omit_the_delivery_notice(): void
    {
        $html = $this->renderOrderTable('delfi', 'Delfi');

        $this->assertStringNotContainsString('Dostupno u roku 20 dana.', $html);
    }

    private function renderOrderTable(string $publisherSlug, string $publisherTitle, bool $showDeliveryWindowNote = false): string
    {
        $publisher = new Publisher([
            'slug' => $publisherSlug,
            'title' => $publisherTitle,
        ]);

        $catalogProduct = new Product([
            'sku' => 'TEST-1',
        ]);
        $catalogProduct->setRelation('publisher', $publisher);

        $orderProduct = new OrderProduct([
            'name' => 'Testna knjiga',
            'quantity' => 1,
            'price' => 15,
            'total' => 15,
        ]);
        $orderProduct->setRelation('product', $catalogProduct);

        $order = new Order([
            'shipping_state' => 'Croatia',
        ]);
        $order->setRelation('products', collect([$orderProduct]));
        $order->setRelation('totals', collect());

        return view('emails.layouts.partials.order-price-table', compact('order', 'showDeliveryWindowNote'))->render();
    }
}
