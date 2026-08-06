<?php

namespace Tests\Feature;

use App\Helpers\Session\CheckoutSession;
use App\Http\Livewire\Front\Checkout;
use App\Services\WoltDrive\WoltDriveService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class CheckoutWoltAvailabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('cache.default', 'array');
        config()->set('session.cart', 'zuzi_cart');

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();

        Schema::create('settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code');
            $table->string('key');
            $table->longText('value')->nullable();
            $table->boolean('json')->default(false);
            $table->timestamps();
        });

        DB::table('settings')->insert([
            'code' => 'shipping',
            'key' => 'list.checkout',
            'json' => true,
            'value' => json_encode([
                $this->shippingMethod('Wolt Drive', 'wolt_drive', 1),
                $this->shippingMethod('GLS dostava', 'gls', 2),
            ]),
        ]);

        session()->start();
        session([config('session.cart') => 'wolt-checkout-test']);
        CheckoutSession::setAddress([
            'fname' => 'Saša',
            'lname' => 'Bijelić',
            'email' => 'sasa@example.test',
            'phone' => '0916300681',
            'address' => 'Ulica vrba 1',
            'city' => 'Zagreb',
            'zip' => '10000',
            'company' => '',
            'oib' => '',
            'state' => 'Croatia',
        ]);
    }

    public function test_unavailable_wolt_is_disabled_and_cannot_be_selected(): void
    {
        $service = Mockery::mock(WoltDriveService::class);
        $service->shouldReceive('checkAddressAvailability')
            ->andReturn([
                'available' => false,
                'error_code' => 'DROPOFF_OUTSIDE_OF_DELIVERY_AREA',
                'message' => 'Wolt Drive nije dostupan za ovu adresu jer je izvan područja dostave.',
            ]);
        $this->app->instance(WoltDriveService::class, $service);

        Livewire::test(Checkout::class, ['step' => 'dostava'])
            ->assertSet('woltAvailable', false)
            ->assertSee('Nije dostupno')
            ->assertSee('Wolt Drive nije dostupan za ovu adresu jer je izvan područja dostave.')
            ->assertSet('shipping', 'gls')
            ->call('selectShipping', 'wolt_drive')
            ->assertSet('shipping', 'gls')
            ->assertHasErrors(['shipping']);

        $this->assertSame('gls', CheckoutSession::getShipping());
    }

    private function shippingMethod(string $title, string $code, int $sortOrder): array
    {
        return [
            'id' => $sortOrder,
            'title' => $title,
            'code' => $code,
            'geo_zone' => 1,
            'status' => true,
            'sort_order' => $sortOrder,
            'data' => [
                'price' => 5,
                'time' => '1-2 dana',
                'short_description' => '',
                'description' => '',
            ],
        ];
    }
}
