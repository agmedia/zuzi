<?php

namespace Tests\Unit;

use App\Mail\StatusCompleted;
use App\Mail\StatusReady;
use App\Models\Back\Orders\Order;
use Tests\TestCase;

class OrderCompletedMailTest extends TestCase
{
    /**
     * @dataProvider personalPickupPayments
     */
    public function test_completed_personal_pickup_email_confirms_completion_without_claiming_shipment(string $paymentCode): void
    {
        $mail = new StatusCompleted($this->order('pickup', $paymentCode));

        $html = $mail->render();

        $this->assertSame('Vaša narudžba je završena - Zuzi Shop', $mail->subject);
        $this->assertStringContainsString('Vaša narudžba je završena', $html);
        $this->assertStringContainsString('osobno preuzimanje', $html);
        $this->assertStringNotContainsString('knjiga je poslana', $html);
        $this->assertStringNotContainsString('spremna u dućanu za preuzimanje', $html);
    }

    public function personalPickupPayments(): array
    {
        return [
            'pay in store' => ['pickup'],
            'bank transfer' => ['bank'],
            'prepaid card' => ['payway'],
        ];
    }

    /**
     * @dataProvider shippedOrders
     */
    public function test_completed_delivery_email_keeps_shipment_wording(string $shippingCode, string $paymentCode): void
    {
        $mail = new StatusCompleted($this->order($shippingCode, $paymentCode));

        $html = $mail->render();

        $this->assertSame('Vasa knjiga je poslana - Zuzi Shop', $mail->subject);
        $this->assertStringContainsString('Vaša knjiga je poslana', $html);
        $this->assertStringContainsString('Obavještavamo Vas da je Vaša knjiga poslana.', $html);
        $this->assertStringNotContainsString('Vaša narudžba je završena', $html);
    }

    public function shippedOrders(): array
    {
        return [
            'courier with pickup payment does not imply in-store collection' => ['gls', 'pickup'],
            'parcel locker' => ['gls_paketomat', 'bank'],
            'BoxNow locker' => ['gls_eu', 'payway'],
            'legacy order without shipping code' => ['', 'pickup'],
        ];
    }

    public function test_ready_for_pickup_email_still_invites_the_customer_to_collect_the_order(): void
    {
        $mail = new StatusReady($this->order('pickup', 'bank'));

        $html = $mail->render();

        $this->assertSame('Vasa knjiga je spremna u ducanu za preuzimanje - Zuzi Shop', $mail->subject);
        $this->assertStringContainsString('Vaša knjiga je spremna u dućanu za preuzimanje', $html);
        $this->assertStringContainsString('Vaša knjiga pripremljena i čeka Vas u dućanu za preuzimanje.', $html);
        $this->assertStringNotContainsString('knjiga je poslana', $html);
    }

    private function order(string $shippingCode, string $paymentCode): Order
    {
        $order = new Order([
            'payment_fname' => 'Ana',
            'payment_lname' => 'Test',
            'payment_email' => 'ana@example.test',
            'payment_code' => $paymentCode,
            'shipping_code' => $shippingCode,
            'shipping_method' => $shippingCode === 'pickup' ? 'Preuzimanje u trgovini' : 'Dostava',
            'shipping_state' => 'Croatia',
        ]);
        $order->id = 123;
        $order->setRelation('products', collect());
        $order->setRelation('totals', collect());

        return $order;
    }
}
