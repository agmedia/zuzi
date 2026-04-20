<?php

namespace Tests\Feature;

use App\Http\Controllers\Back\Widget\WidgetController;
use App\Models\Back\Widget\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function createProduct(string $name, string $sku): int
    {
        return (int) DB::table('products')->insertGetId([
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
        ]);
    }
}
