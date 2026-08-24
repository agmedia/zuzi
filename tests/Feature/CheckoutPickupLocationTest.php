<?php

namespace Tests\Feature;

use App\Helpers\Session\CheckoutSession;
use App\Http\Livewire\Front\Checkout;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutPickupLocationTest extends TestCase
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
            'key' => 'list.checkout-pickup-location',
            'json' => true,
            'value' => json_encode([
                $this->shippingMethod('BoxNow paketomat', 'gls_eu', 1),
                $this->shippingMethod('GLS paketomat', 'gls_paketomat', 2),
                $this->shippingMethod('BoxNow paketomat novi kod', 'boxnow', 3),
                $this->shippingMethod('GLS dostava', 'gls', 4),
            ]),
        ]);

        session()->start();
        session([config('session.cart') => 'pickup-location-checkout-test']);
        CheckoutSession::setAddress([
            'fname' => 'Ana',
            'lname' => 'Anić',
            'email' => 'ana@example.test',
            'phone' => '0911234567',
            'address' => 'Ilica 1',
            'city' => 'Zagreb',
            'zip' => '10000',
            'company' => '',
            'oib' => '',
            'state' => 'Croatia',
        ]);
    }

    public function test_switching_shipping_method_clears_stale_pickup_location_and_payment(): void
    {
        CheckoutSession::setShipping('gls_eu');
        CheckoutSession::setComment('10000, Stari BoxNow_1234');
        CheckoutSession::setPayment('corvus');
        CheckoutSession::setPaymentWallet('google_pay');

        Livewire::test(Checkout::class, ['step' => 'dostava'])
            ->assertSet('shipping', 'gls_eu')
            ->assertSet('comment', '10000, Stari BoxNow_1234')
            ->assertSet('payment', 'corvus')
            // wire:model updates the property before the row's wire:click handler runs.
            ->set('shipping', 'gls_paketomat')
            ->call('selectShipping', 'gls_paketomat')
            ->assertSet('shipping', 'gls_paketomat')
            ->assertSet('comment', '')
            ->assertSet('payment', '')
            ->assertSet('view_comment', false)
            ->assertSet('view_commentt', true);

        $this->assertSame('gls_paketomat', CheckoutSession::getShipping());
        $this->assertFalse(CheckoutSession::hasComment());
        $this->assertFalse(CheckoutSession::hasPayment());
        $this->assertFalse(CheckoutSession::hasPaymentWallet());
    }

    public function test_boxnow_code_is_supported_and_requires_a_locker_id(): void
    {
        CheckoutSession::setShipping('boxnow');

        $component = Livewire::test(Checkout::class, ['step' => 'dostava'])
            ->assertSet('shipping', 'boxnow')
            ->assertSet('view_comment', true)
            ->assertSee('Odaberite BOX NOW lokaciju')
            ->set('comment', 'BoxNow paketomat bez identifikatora')
            ->call('changeStep', 'placanje', false)
            ->assertHasErrors(['comment']);

        $component
            ->set('comment', '10000, Ilica 1_5678')
            ->call('changeStep', 'placanje', false)
            ->assertHasNoErrors(['comment'])
            ->assertSet('step', 'placanje');

        $this->assertSame('10000, Ilica 1_5678', CheckoutSession::getComment());
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
                'price' => 1.5,
                'time' => '1-2 dana',
                'short_description' => '',
                'description' => '',
            ],
        ];
    }
}
