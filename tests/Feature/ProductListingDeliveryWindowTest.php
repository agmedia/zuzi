<?php

namespace Tests\Feature;

use App\Models\Front\Catalog\Product;
use App\Models\Front\Catalog\Publisher;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductListingDeliveryWindowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @dataProvider publishersWithTwentyDayDelivery
     */
    public function test_listing_card_shows_the_delivery_tooltip_for_matching_publishers(
        string $slug,
        string $title
    ): void {
        $html = $this->renderProductCard($slug, $title);

        $this->assertStringContainsString('ci-time', $html);
        $this->assertStringContainsString('title="Dostupno u roku 20 dana."', $html);
        $this->assertStringContainsString('aria-label="Dostupno u roku 20 dana."', $html);
    }

    public function publishersWithTwentyDayDelivery(): array
    {
        return [
            'Delfi' => ['delfi', 'Delfi'],
            'Laguna' => ['laguna', 'Laguna'],
        ];
    }

    public function test_listing_card_does_not_show_the_delivery_tooltip_for_delfin(): void
    {
        $html = $this->renderProductCard('delfin', 'DELFIN');

        $this->assertStringNotContainsString('ci-time', $html);
        $this->assertStringNotContainsString('title="Dostupno u roku 20 dana."', $html);
        $this->assertStringNotContainsString('aria-label="Dostupno u roku 20 dana."', $html);
    }

    private function renderProductCard(string $publisherSlug, string $publisherTitle): string
    {
        $publisher = new Publisher([
            'slug' => $publisherSlug,
            'title' => $publisherTitle,
        ]);

        $product = new Product([
            'name' => 'Testna knjiga',
            'url' => '/testna-knjiga',
            'image' => '/media/testna-knjiga.webp',
            'price' => 15,
            'special' => null,
            'quantity' => 1,
            'delivery_24h' => 0,
        ]);
        $product->id = 123;
        $product->setRelation('publisher', $publisher);
        $product->setRelation('author', null);
        $product->setRelation('action', null);
        $product->setRelation('categories', new EloquentCollection());

        return view('front.catalog.category.product', compact('product'))->render();
    }
}
