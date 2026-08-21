<?php

namespace Tests\Unit;

use App\Models\Back\Settings\Settings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SettingsListCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        Cache::store('array')->flush();
        DB::purge('sqlite');

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('key');
            $table->text('value');
            $table->boolean('json')->default(false);
            $table->timestamps();
        });
    }

    public function test_updating_list_item_invalidates_cached_list(): void
    {
        Settings::create([
            'code' => 'shipping',
            'key' => 'list.gls_world',
            'value' => json_encode([$this->shippingMethod(100)]),
            'json' => true,
        ]);

        $this->assertSame(
            100,
            Settings::getList('shipping', 'list.%', false)->first()->data->free_shipping_from
        );

        $this->assertTrue(Settings::setListItem(
            'shipping',
            'list.gls_world',
            $this->shippingMethod(1000000)
        ));

        $this->assertSame(
            1000000,
            Settings::getList('shipping', 'list.%', false)->first()->data->free_shipping_from
        );
    }

    private function shippingMethod(int $threshold): array
    {
        return [
            'title' => 'Dostava EU',
            'code' => 'gls_world',
            'data' => [
                'price' => 15,
                'free_shipping_from' => $threshold,
            ],
            'geo_zone' => 2,
            'status' => true,
            'sort_order' => 2,
        ];
    }
}
