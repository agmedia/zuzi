<?php

namespace Tests\Unit;

use App\Services\WoltDrive\WoltDriveService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WoltDriveServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'services.wolt.url' => 'https://wolt.example.test',
            'services.wolt.api_key' => 'test-api-key',
            'services.wolt.venue_id' => 'test-venue',
            'services.wolt.availability_cache_seconds' => 300,
        ]);

        Cache::flush();
    }

    public function test_it_uses_shipment_promise_to_check_checkout_address(): void
    {
        Http::fake([
            'https://wolt.example.test/v1/venues/test-venue/shipment-promises' => Http::response([
                'id' => 'promise-1',
                'is_binding' => true,
            ], 201),
        ]);

        $result = app(WoltDriveService::class)->checkAddressAvailability($this->address());

        $this->assertTrue($result['available']);
        $this->assertNull($result['message']);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://wolt.example.test/v1/venues/test-venue/shipment-promises'
                && $request->hasHeader('Authorization', 'Bearer test-api-key')
                && $request['street'] === 'Ulica vrba 1'
                && $request['city'] === 'Zagreb'
                && $request['post_code'] === '10000';
        });
    }

    public function test_it_marks_wolt_unavailable_when_dropoff_is_outside_delivery_area(): void
    {
        Http::fake([
            'https://wolt.example.test/*' => Http::response([
                'error_code' => 'DROPOFF_OUTSIDE_OF_DELIVERY_AREA',
                'reason' => 'Dropoff location is outside of the delivery area',
                'details' => 'Delivery distance is longer than maximum allowed distance',
            ], 400),
        ]);

        $result = app(WoltDriveService::class)->checkAddressAvailability($this->address());

        $this->assertFalse($result['available']);
        $this->assertSame('DROPOFF_OUTSIDE_OF_DELIVERY_AREA', $result['error_code']);
        $this->assertSame(
            'Wolt Drive nije dostupan za ovu adresu jer je izvan područja dostave. Odaberite drugi način dostave.',
            $result['message']
        );
    }

    public function test_it_fails_closed_when_wolt_cannot_confirm_availability(): void
    {
        Http::fake([
            'https://wolt.example.test/*' => Http::response(['message' => 'Server error'], 500),
        ]);

        $result = app(WoltDriveService::class)->checkAddressAvailability($this->address());

        $this->assertFalse($result['available']);
        $this->assertSame('WOLT_AVAILABILITY_CHECK_FAILED', $result['error_code']);
        $this->assertStringContainsString('trenutačno nije moguće potvrditi', $result['message']);
    }

    public function test_it_does_not_offer_wolt_for_a_non_binding_promise(): void
    {
        Http::fake([
            'https://wolt.example.test/*' => Http::response([
                'id' => 'promise-1',
                'is_binding' => false,
            ], 201),
        ]);

        $result = app(WoltDriveService::class)->checkAddressAvailability($this->address());

        $this->assertFalse($result['available']);
        $this->assertSame('NON_BINDING_SHIPMENT_PROMISE', $result['error_code']);
        $this->assertStringContainsString('dovoljno precizno potvrditi', $result['message']);
    }

    private function address(): array
    {
        return [
            'address' => 'Ulica vrba 1',
            'city' => 'Zagreb',
            'zip' => '10000',
            'state' => 'Croatia',
        ];
    }
}
