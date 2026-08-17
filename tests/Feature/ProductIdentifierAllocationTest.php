<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProductIdentifierAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductIdentifierAllocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_create_form_reserves_the_first_free_sku_and_itemid_independently(): void
    {
        $this->createProduct('Postojeći artikl 1', '1', 2);
        $this->createProduct('Postojeći artikl 2', '3', 1);

        $response = $this->get(route('products.create'));

        $response->assertOk();
        $response->assertViewHas('identifier_reservation', function (array $reservation) {
            return $reservation['sku'] === 2 && $reservation['itemid'] === 3;
        });
        $response->assertSee('name="sku"', false);
        $response->assertSee('name="itemid"', false);
        $response->assertSee('readonly aria-readonly="true"', false);

        $reservation = $response->viewData('identifier_reservation');

        $this->assertDatabaseHas('product_identifier_reservations', [
            'token' => $reservation['token'],
            'sku' => 2,
            'itemid' => 3,
        ]);
    }

    public function test_returning_to_create_form_reuses_the_unfinished_reservation(): void
    {
        $firstReservation = $this->get(route('products.create'))
            ->viewData('identifier_reservation');

        $secondReservation = $this->get(route('products.create'))
            ->viewData('identifier_reservation');

        $this->assertSame(1, $firstReservation['sku']);
        $this->assertSame(1, $firstReservation['itemid']);
        $this->assertSame($firstReservation, $secondReservation);
    }

    public function test_store_confirms_reserved_identifiers_and_ignores_manually_submitted_values(): void
    {
        $this->createProduct('Postojeći artikl 1', '1', 2);
        $this->createProduct('Postojeći artikl 2', '3', 1);
        $categoryId = $this->createCategory();

        $reservation = $this->get(route('products.create'))
            ->viewData('identifier_reservation');

        $response = $this->post(route('products.store'), [
            ProductIdentifierAllocator::SESSION_KEY => $reservation['token'],
            'name' => 'Automatski numeriran artikl',
            'sku' => 999,
            'itemid' => 999,
            'price' => 10,
            'quantity' => 1,
            'tax_id' => 1,
            'category' => [$categoryId],
        ]);

        $response->assertRedirect(route('products'));
        $response->assertSessionMissing(ProductIdentifierAllocator::SESSION_KEY);
        $this->assertDatabaseHas('products', [
            'name' => 'Automatski numeriran artikl',
            'sku' => '2',
            'itemid' => 3,
        ]);
        $this->assertDatabaseMissing('products', [
            'name' => 'Automatski numeriran artikl',
            'sku' => '999',
        ]);
        $this->assertDatabaseMissing('product_identifier_reservations', [
            'token' => $reservation['token'],
        ]);
    }

    public function test_expired_reservations_are_released_for_reuse(): void
    {
        $expiredReservation = $this->get(route('products.create'))
            ->viewData('identifier_reservation');

        DB::table('product_identifier_reservations')
            ->where('token', $expiredReservation['token'])
            ->update(['expires_at' => now()->subMinute()]);

        $newReservation = $this->get(route('products.create'))
            ->viewData('identifier_reservation');

        $this->assertSame(1, $newReservation['sku']);
        $this->assertSame(1, $newReservation['itemid']);
        $this->assertNotSame($expiredReservation['token'], $newReservation['token']);
        $this->assertDatabaseMissing('product_identifier_reservations', [
            'token' => $expiredReservation['token'],
        ]);
    }

    private function createProduct(string $name, string $sku, int $itemid): int
    {
        return DB::table('products')->insertGetId([
            'name' => $name,
            'sku' => $sku,
            'itemid' => $itemid,
            'slug' => Str::slug($name),
            'url' => '/',
            'price' => 10,
            'quantity' => 1,
            'tax_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createCategory(): int
    {
        return DB::table('categories')->insertGetId([
            'parent_id' => 0,
            'group' => 'Knjige',
            'title' => 'Test kategorija',
            'slug' => 'test-kategorija',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
