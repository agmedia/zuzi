<?php

namespace Tests\Feature;

use App\Http\Controllers\Front\CatalogRouteController;
use App\Models\Back\Marketing\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CombinedCategoryActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_combined_category_action_applies_batch_discounts_and_skips_new_books(): void
    {
        $categoryA = $this->createCategory('Beletristika');
        $categoryB = $this->createCategory('Časopisi');

        $usedCategoryAIds = collect(range(1, 12))->map(function (int $index) use ($categoryA) {
            return $this->createProduct("Rabljena A {$index}", "A{$index}", 100, 'Odlično', $categoryA);
        });

        $newCategoryAIds = collect(range(1, 4))->map(function (int $index) use ($categoryA) {
            return $this->createProduct("Novo A {$index}", "AN{$index}", 100, 'NOVO', $categoryA);
        });

        $newBookCategoryAIds = collect(range(1, 3))->map(function (int $index) use ($categoryA) {
            return $this->createProduct("Nova knjiga A {$index}", "AK{$index}", 100, 'Nova knjiga', $categoryA);
        });

        $allCategoryBIds = collect(range(1, 7))->map(function (int $index) use ($categoryB) {
            $condition = $index % 2 === 0 ? 'NOVO' : 'Dobro';

            return $this->createProduct("B knjiga {$index}", "B{$index}", 80, $condition, $categoryB);
        });

        $request = Request::create('/action', 'POST', [
            'title' => 'Proljetna kombinirana akcija',
            'group' => 'combined_category',
            'type' => 'P',
            'discount' => 0,
            'status' => 'on',
            'combined_categories' => [
                [
                    'category_id' => $categoryA,
                    'discount' => 20,
                    'apply_to' => 'used',
                ],
                [
                    'category_id' => $categoryB,
                    'discount' => 10,
                    'apply_to' => 'all',
                ],
            ],
        ]);

        $storedAction = (new Action())->validateRequest($request)->create();

        $this->assertInstanceOf(Action::class, $storedAction);
        $this->assertSame('combined_category', $storedAction->group);
        $this->assertSame('Kombinirano', $storedAction->discount_text);
        $this->assertSame(20.0, (float) $storedAction->discount);
        $this->assertCount(2, $storedAction->data['combined_categories'] ?? []);

        $usedCategoryAIds->each(function (int $productId) use ($storedAction) {
            $product = DB::table('products')->where('id', $productId)->first(['action_id', 'special']);

            $this->assertSame($storedAction->id, (int) $product->action_id);
            $this->assertSame(80.0, (float) $product->special);
        });

        $newCategoryAIds
            ->merge($newBookCategoryAIds)
            ->each(function (int $productId) {
                $product = DB::table('products')->where('id', $productId)->first(['action_id', 'special']);

                $this->assertSame(0, (int) $product->action_id);
                $this->assertNull($product->special);
            });

        $allCategoryBIds->each(function (int $productId) use ($storedAction) {
            $product = DB::table('products')->where('id', $productId)->first(['action_id', 'special']);

            $this->assertSame($storedAction->id, (int) $product->action_id);
            $this->assertSame(72.0, (float) $product->special);
        });
    }

    public function test_removing_last_action_clears_leftover_unlocked_specials(): void
    {
        $category = $this->createCategory('Poezija');
        $productId = $this->createProduct('Rabljena poezija', 'P1', 50, 'Dobro', $category);
        $orphanProductId = $this->createProduct('Zaostali popust', 'P2', 40, 'Dobro', $category);

        $request = Request::create('/action', 'POST', [
            'title' => 'Test akcija',
            'group' => 'combined_category',
            'type' => 'P',
            'discount' => 0,
            'status' => 'on',
            'combined_categories' => [
                [
                    'category_id' => $category,
                    'discount' => 20,
                    'apply_to' => 'all',
                ],
            ],
        ]);

        $storedAction = (new Action())->validateRequest($request)->create();

        DB::table('products')
            ->where('id', $orphanProductId)
            ->update([
                'action_id' => 0,
                'special' => 25,
                'special_from' => Carbon::now()->subDay(),
                'special_to' => Carbon::now()->addDay(),
                'special_lock' => 0,
            ]);

        $this->assertTrue($storedAction->remove());

        collect([$productId, $orphanProductId])->each(function (int $id) {
            $product = DB::table('products')->where('id', $id)->first(['action_id', 'special', 'special_from', 'special_to']);

            $this->assertSame(0, (int) $product->action_id);
            $this->assertNull($product->special);
            $this->assertNull($product->special_from);
            $this->assertNull($product->special_to);
        });
    }

    public function test_future_combined_category_action_switches_landing_copy_and_products_on_start_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-19 12:00:00'));

        $category = $this->createCategory('Noć knjige test');
        $productId = $this->createProduct('Rabljena noć knjige', 'NK1', 100, 'Dobro', $category);

        $request = Request::create('/action', 'POST', [
            'title' => 'Noć knjige kombinirana akcija',
            'group' => 'combined_category',
            'type' => 'P',
            'discount' => 0,
            'status' => 'on',
            'date_start' => '2026-04-20 00:00:00',
            'date_end' => '2026-05-01 23:59:59',
            'combined_categories' => [
                [
                    'category_id' => $category,
                    'discount' => 20,
                    'apply_to' => 'all',
                ],
            ],
        ]);

        $storedAction = (new Action())->validateRequest($request)->create();

        $productBeforeStart = DB::table('products')->where('id', $productId)->first(['action_id', 'special']);
        $landingBeforeStart = $this->resolveActionLanding();

        $this->assertSame(0, (int) $productBeforeStart->action_id);
        $this->assertNull($productBeforeStart->special);
        $this->assertSame('AKCIJSKA PONUDA', $landingBeforeStart['eyebrow']);
        $this->assertSame('Akcijska ponuda', $landingBeforeStart['title']);

        Carbon::setTestNow(Carbon::parse('2026-04-20 00:05:00'));
        Artisan::call('sync:category-actions');

        $productAfterStart = DB::table('products')->where('id', $productId)->first(['action_id', 'special', 'special_from']);
        $landingAfterStart = $this->resolveActionLanding();

        $this->assertSame($storedAction->id, (int) $productAfterStart->action_id);
        $this->assertSame(80.0, (float) $productAfterStart->special);
        $this->assertSame('2026-04-20 00:00:00', $productAfterStart->special_from);
        $this->assertSame('NOĆ KNJIGE 🌙', $landingAfterStart['eyebrow']);
        $this->assertSame('Noć knjige 20.4. - 1.5. - posebna akcijska ponuda 🌙', $landingAfterStart['title']);
        $this->assertCount(1, $landingAfterStart['categories']);
        $this->assertSame('Noć knjige test', $landingAfterStart['categories'][0]['title']);
        $this->assertSame('20%', $landingAfterStart['categories'][0]['discount']);

        Carbon::setTestNow();
    }

    public function test_action_group_fallback_is_used_when_disabled_group_select_is_not_submitted(): void
    {
        $category = $this->createCategory('Fallback kategorija');

        $request = Request::create('/action', 'POST', [
            'title' => 'Fallback akcija',
            'action_group' => 'category',
            'type' => 'P',
            'discount' => 10,
            'status' => 'on',
            'action_list' => [
                $category => $category,
            ],
        ]);

        $storedAction = (new Action())->validateRequest($request)->create();

        $this->assertInstanceOf(Action::class, $storedAction);
        $this->assertSame('category', $storedAction->group);
    }

    private function resolveActionLanding(): array
    {
        $controller = app(CatalogRouteController::class);
        $method = new \ReflectionMethod($controller, 'resolveActionLanding');
        $method->setAccessible(true);

        return $method->invoke($controller, null, null);
    }

    private function createCategory(string $title, int $parentId = 0): int
    {
        return (int) DB::table('categories')->insertGetId([
            'parent_id' => $parentId,
            'title' => $title,
            'description' => null,
            'meta_title' => null,
            'meta_description' => null,
            'image' => 'media/avatars/avatar0.jpg',
            'group' => 'knjige',
            'lang' => 'hr',
            'sort_order' => 0,
            'status' => 1,
            'slug' => Str::slug($title . '-' . $parentId . '-' . uniqid()),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function createProduct(string $name, string $sku, float $price, ?string $condition, int $categoryId): int
    {
        $productId = (int) DB::table('products')->insertGetId([
            'author_id' => 0,
            'publisher_id' => 0,
            'action_id' => 0,
            'name' => $name,
            'sku' => $sku,
            'ean' => null,
            'description' => null,
            'slug' => Str::slug($name . '-' . $sku),
            'url' => '/proizvod/' . Str::slug($name . '-' . $sku),
            'image' => null,
            'price' => $price,
            'quantity' => 10,
            'tax_id' => 1,
            'special' => null,
            'special_from' => null,
            'special_to' => null,
            'special_lock' => 0,
            'meta_title' => $name,
            'meta_description' => null,
            'related_products' => null,
            'pages' => null,
            'dimensions' => null,
            'origin' => null,
            'letter' => null,
            'condition' => $condition,
            'binding' => null,
            'year' => null,
            'viewed' => 0,
            'sort_order' => 0,
            'push' => 0,
            'status' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        DB::table('product_category')->insert([
            'product_id' => $productId,
            'category_id' => $categoryId,
        ]);

        return $productId;
    }
}
