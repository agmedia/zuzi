<?php

namespace Tests\Unit;

use App\Services\ProductIdentifierAllocator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductIdentifierAllocatorTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        config([
            'database.default' => 'identifier_allocator_test',
            'database.connections.identifier_allocator_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        DB::purge('identifier_allocator_test');

        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sku', 14)->index();
            $table->unsignedBigInteger('itemid')->nullable()->unique();
        });

        Schema::create('product_identifier_allocation_locks', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
        });

        DB::table('product_identifier_allocation_locks')->insert(['id' => 1]);

        Schema::create('product_identifier_reservations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('token')->unique();
            $table->unsignedBigInteger('sku')->unique();
            $table->unsignedBigInteger('itemid')->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('identifier_allocator_test');
        config(['database.default' => $this->originalConnection]);

        parent::tearDown();
    }

    public function test_reservations_skip_existing_products_and_other_active_reservations(): void
    {
        DB::table('products')->insert([
            ['sku' => '1', 'itemid' => 2],
            ['sku' => '3', 'itemid' => 1],
        ]);

        $allocator = app(ProductIdentifierAllocator::class);
        $first = $allocator->reserve();
        $second = $allocator->reserve();

        $this->assertSame(['sku' => 2, 'itemid' => 3], $this->identifiers($first));
        $this->assertSame(['sku' => 4, 'itemid' => 4], $this->identifiers($second));
    }

    public function test_confirmation_uses_the_reserved_numbers_and_releases_the_reservation(): void
    {
        $allocator = app(ProductIdentifierAllocator::class);
        $reservation = $allocator->reserve();

        $productId = $allocator->confirm($reservation['token'], function (array $identifiers) {
            return DB::table('products')->insertGetId($identifiers);
        });

        $this->assertIsInt($productId);
        $this->assertDatabaseHas('products', ['sku' => '1', 'itemid' => 1]);
        $this->assertDatabaseMissing('product_identifier_reservations', [
            'token' => $reservation['token'],
        ]);
    }

    public function test_expired_reservation_numbers_are_available_again(): void
    {
        $allocator = app(ProductIdentifierAllocator::class);
        $expired = $allocator->reserve();

        DB::table('product_identifier_reservations')
            ->where('token', $expired['token'])
            ->update(['expires_at' => now()->subMinute()]);

        $replacement = $allocator->reserve();

        $this->assertSame(['sku' => 1, 'itemid' => 1], $this->identifiers($replacement));
        $this->assertNotSame($expired['token'], $replacement['token']);
    }

    /**
     * @param array{token: string, sku: int, itemid: int} $reservation
     * @return array{sku: int, itemid: int}
     */
    private function identifiers(array $reservation): array
    {
        return [
            'sku' => $reservation['sku'],
            'itemid' => $reservation['itemid'],
        ];
    }
}
