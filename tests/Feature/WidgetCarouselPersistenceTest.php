<?php

namespace Tests\Feature;

use App\Helpers\Helper;
use App\Http\Controllers\Back\Widget\WidgetController;
use App\Models\Back\Widget\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WidgetCarouselPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_target_fallback_is_used_when_disabled_target_select_is_not_submitted(): void
    {
        $groupId = $this->createWidgetGroup('product_carousel', 'Naslovni carousel');
        $productId = $this->createProduct('Carousel artikl', 'WC1');

        $request = Request::create('/widget', 'POST', [
            'title' => 'Istaknuti artikli',
            'group_id' => $groupId,
            'group_template' => 'product_carousel',
            'action_group' => 'product',
            'action_list' => [
                $productId => $productId,
            ],
            'status' => 'on',
        ]);

        $storedWidget = (new Widget())->validateRequest($request)->setUrl()->store();

        $this->assertInstanceOf(Widget::class, $storedWidget);

        $data = unserialize($storedWidget->data);

        $this->assertSame('product', $data['target'] ?? null);
        $this->assertArrayNotHasKey('action_group', $data);
        $this->assertSame([$productId], array_map('intval', array_values($data['list'] ?? [])));
    }

    public function test_widget_edit_uses_target_key_when_restoring_selected_items(): void
    {
        $groupId = $this->createWidgetGroup('product_carousel', 'Naslovni carousel');
        $productId = $this->createProduct('Spremljeni artikl', 'WC2');

        $widgetId = (int) DB::table('widgets')->insertGetId([
            'group_id' => $groupId,
            'title' => 'Istaknuti artikli',
            'subtitle' => null,
            'description' => null,
            'data' => serialize([
                'target' => 'product',
                'list' => [
                    $productId => $productId,
                ],
                'css' => 'home-carousel',
            ]),
            'image' => null,
            'link' => null,
            'link_id' => null,
            'url' => '/',
            'badge' => null,
            'width' => null,
            'sort_order' => 0,
            'status' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $response = app(WidgetController::class)->edit($widgetId);
        $viewData = $response->getData();
        $restoredLinks = json_decode($viewData['widget']->links, true) ?? [];

        $this->assertSame('product', $viewData['widget']->target);
        $this->assertSame([$productId], array_map('intval', array_values($restoredLinks)));
    }

    public function test_widget_stores_category_discount_price_mix_flag(): void
    {
        $groupId = $this->createWidgetGroup('product_carousel', 'Kategorijski carousel');
        $categoryId = $this->createCategory('Stripovi');

        $request = Request::create('/widget', 'POST', [
            'title' => 'Stripovi',
            'group_id' => $groupId,
            'group_template' => 'product_carousel',
            'action_group' => 'category',
            'action_list' => [
                $categoryId => $categoryId,
            ],
            'category_discount_price_mix' => 'on',
            'status' => 'on',
        ]);

        $storedWidget = (new Widget())->validateRequest($request)->setUrl()->store();

        $this->assertInstanceOf(Widget::class, $storedWidget);

        $data = unserialize($storedWidget->data);

        $this->assertSame('category', $data['target'] ?? null);
        $this->assertSame('on', $data['category_discount_price_mix'] ?? null);
    }

    public function test_category_product_carousel_checkbox_returns_only_highest_priced_discounted_products(): void
    {
        $categoryId = $this->createCategory('Preporuke');

        $nonDiscountedMostExpensiveId = $this->createProduct('Najskuplji bez popusta', 'NODISC1', [
            'price' => 500,
            'image' => 'media/test/nodisc1.jpg',
        ]);
        $largestDiscountId = $this->createProduct('Najveci popust', 'DISC1', [
            'price' => 100,
            'special' => 20,
            'special_from' => Carbon::now()->subDay(),
            'special_to' => Carbon::now()->addDay(),
            'image' => 'media/test/disc1.jpg',
        ]);
        $mostExpensiveId = $this->createProduct('Najskuplji artikl', 'PRICE1', [
            'price' => 400,
            'special' => 360,
            'special_from' => Carbon::now()->subDay(),
            'special_to' => Carbon::now()->addDay(),
            'image' => 'media/test/price1.jpg',
        ]);
        $secondLargestDiscountId = $this->createProduct('Drugi popust', 'DISC2', [
            'price' => 200,
            'special' => 80,
            'special_from' => Carbon::now()->subDay(),
            'special_to' => Carbon::now()->addDay(),
            'image' => 'media/test/disc2.jpg',
        ]);
        $secondMostExpensiveId = $this->createProduct('Drugi najskuplji', 'PRICE2', [
            'price' => 350,
            'special' => 320,
            'special_from' => Carbon::now()->subDay(),
            'special_to' => Carbon::now()->addDay(),
            'image' => 'media/test/price2.jpg',
        ]);
        $thirdMostExpensiveId = $this->createProduct('Treci najskuplji', 'PRICE3', [
            'price' => 250,
            'special' => 230,
            'special_from' => Carbon::now()->subDay(),
            'special_to' => Carbon::now()->addDay(),
            'image' => 'media/test/price3.jpg',
        ]);

        collect([
            $nonDiscountedMostExpensiveId,
            $largestDiscountId,
            $mostExpensiveId,
            $secondLargestDiscountId,
            $secondMostExpensiveId,
            $thirdMostExpensiveId,
        ])->each(function (int $productId) use ($categoryId) {
            $this->attachProductToCategory($productId, $categoryId);
        });

        $productIds = $this->invokeCategoryProducts([
            'target' => 'category',
            'list' => [$categoryId],
            'category_discount_price_mix' => 'on',
        ])->get()->pluck('id')->all();

        $this->assertNotContains($nonDiscountedMostExpensiveId, $productIds);
        $this->assertSame([
            $mostExpensiveId,
            $secondMostExpensiveId,
            $thirdMostExpensiveId,
            $secondLargestDiscountId,
            $largestDiscountId,
        ], array_slice($productIds, 0, 5));
    }

    public function test_category_product_carousel_keeps_price_desc_order_without_mix_flag(): void
    {
        $categoryId = $this->createCategory('Cjenovni poredak');

        $lowestPriceId = $this->createProduct('Najniza cijena', 'BASE1', [
            'price' => 100,
            'image' => 'media/test/base1.jpg',
        ]);
        $highestPriceId = $this->createProduct('Najvisa cijena', 'BASE2', [
            'price' => 300,
            'image' => 'media/test/base2.jpg',
        ]);
        $middlePriceId = $this->createProduct('Srednja cijena', 'BASE3', [
            'price' => 200,
            'image' => 'media/test/base3.jpg',
        ]);

        collect([$lowestPriceId, $highestPriceId, $middlePriceId])->each(function (int $productId) use ($categoryId) {
            $this->attachProductToCategory($productId, $categoryId);
        });

        $productIds = $this->invokeCategoryProducts([
            'target' => 'category',
            'list' => [$categoryId],
        ])->get()->pluck('id')->all();

        $this->assertSame([
            $highestPriceId,
            $middlePriceId,
            $lowestPriceId,
        ], array_slice($productIds, 0, 3));
    }

    private function createWidgetGroup(string $template, string $title): int
    {
        return (int) DB::table('widget_groups')->insertGetId([
            'template' => $template,
            'type' => null,
            'title' => $title,
            'slug' => Str::slug($title . '-' . uniqid()),
            'width' => '12',
            'status' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function createCategory(string $title, int $parentId = 0): int
    {
        return (int) DB::table('categories')->insertGetId([
            'parent_id' => $parentId,
            'title' => $title,
            'description' => null,
            'meta_title' => null,
            'meta_description' => null,
            'image' => 'media/test/category.jpg',
            'group' => 'knjige',
            'lang' => 'hr',
            'sort_order' => 0,
            'status' => 1,
            'slug' => Str::slug($title . '-' . $parentId . '-' . uniqid()),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function createProduct(string $name, string $sku, array $overrides = []): int
    {
        return (int) DB::table('products')->insertGetId(array_merge([
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
            'price' => 10,
            'quantity' => 5,
            'tax_id' => 1,
            'special' => null,
            'special_from' => null,
            'special_to' => null,
            'meta_title' => $name,
            'meta_description' => null,
            'related_products' => null,
            'pages' => null,
            'dimensions' => null,
            'origin' => null,
            'letter' => null,
            'condition' => null,
            'binding' => null,
            'year' => null,
            'viewed' => 0,
            'sort_order' => 0,
            'push' => 0,
            'status' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ], $overrides));
    }

    private function attachProductToCategory(int $productId, int $categoryId): void
    {
        DB::table('product_category')->insert([
            'product_id' => $productId,
            'category_id' => $categoryId,
        ]);
    }

    private function invokeCategoryProducts(array $data): Builder
    {
        $method = new \ReflectionMethod(Helper::class, 'categoryProducts');
        $method->setAccessible(true);

        /** @var Builder $query */
        $query = $method->invoke(null, $data);

        return $query;
    }
}
