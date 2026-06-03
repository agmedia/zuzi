<?php

namespace Tests\Unit;

use App\Services\Shipping\BoxNowService;
use Tests\TestCase;

class BoxNowWebhookPayloadTest extends TestCase
{
    public function test_webhook_payload_uses_event_as_display_status(): void
    {
        $tracking = app(BoxNowService::class)->normalizeWebhookPayload([
            'time' => '2026-06-03T10:00:00Z',
            'subject' => '0514173794',
            'data' => [
                'parcelId' => '0514173794',
                'parcelState' => 'new',
                'event' => 'final-destination',
                'orderNumber' => '1234',
                'time' => '2026-06-03T11:00:00Z',
            ],
        ]);

        $this->assertSame('boxnow', $tracking['carrier']);
        $this->assertSame('1234', $tracking['order_number']);
        $this->assertSame('0514173794', $tracking['parcel_id']);
        $this->assertSame('final-destination', $tracking['status_code']);
        $this->assertSame('Paket se nalazi u pretincu', $tracking['status']);
    }

    public function test_webhook_signature_accepts_configured_hmac(): void
    {
        config(['services.boxnow.webhook_secret' => 'secret']);

        $body = '{"data":{"event":"delivered"}}';
        $signature = hash_hmac('sha256', $body, 'secret');

        $this->assertTrue(app(BoxNowService::class)->verifyWebhookSignature($body, $signature));
        $this->assertFalse(app(BoxNowService::class)->verifyWebhookSignature($body, 'invalid'));
    }
}
