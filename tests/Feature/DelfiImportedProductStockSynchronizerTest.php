<?php

namespace Tests\Feature;

use App\Models\Back\Catalog\DelfiImportProduct;
use App\Models\Back\Catalog\Product\Product;
use App\Services\Delfi\DelfiImportedProductStockSynchronizer;
use App\Services\Delfi\DelfiImportSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DelfiImportedProductStockSynchronizerTest extends TestCase
{
    use RefreshDatabase;

    private int $nextIdentifier = 800000;

    public function test_it_zeroes_unavailable_imports_and_restores_available_imports(): void
    {
        app(DelfiImportSettings::class)->save(['default_quantity' => 5]);

        $unavailable = $this->product('Nedostupna uvezena knjiga', 5);
        $availableAgain = $this->product('Ponovno dostupna knjiga', 0);
        $stillAvailable = $this->product('Još dostupna knjiga', 3);
        $removedFromFeed = $this->product('Uklonjena iz feeda', 5);
        $matchedButNotImported = $this->product('Samo pronađena knjiga', 7);
        $unrelated = $this->product('Nepovezana knjiga', 9);

        $this->source($unavailable, 'out of stock');
        $this->source($availableAgain, 'in stock');
        $this->source($stillAvailable, 'in stock');
        $this->source($removedFromFeed, 'out of stock', true, false);
        $this->source($matchedButNotImported, 'out of stock', false);

        $result = app(DelfiImportedProductStockSynchronizer::class)->sync();

        $this->assertSame(2, $result['zeroed']);
        $this->assertSame(1, $result['restored']);
        $this->assertSame(5, $result['default_quantity']);
        $this->assertSame(0, (int) $unavailable->fresh()->quantity);
        $this->assertSame(5, (int) $availableAgain->fresh()->quantity);
        $this->assertSame(3, (int) $stillAvailable->fresh()->quantity);
        $this->assertSame(0, (int) $removedFromFeed->fresh()->quantity);
        $this->assertSame(7, (int) $matchedButNotImported->fresh()->quantity);
        $this->assertSame(9, (int) $unrelated->fresh()->quantity);
    }

    private function product(string $name, int $quantity): Product
    {
        $identifier = ++$this->nextIdentifier;

        return Product::query()->create([
            'name' => $name,
            'sku' => (string) $identifier,
            'itemid' => $identifier,
            'slug' => Str::slug($name),
            'url' => '/',
            'price' => 10,
            'quantity' => $quantity,
            'tax_id' => 1,
        ]);
    }

    private function source(
        Product $product,
        string $availability,
        bool $imported = true,
        bool $current = true
    ): DelfiImportProduct {
        $externalId = (string) Str::uuid();

        return DelfiImportProduct::query()->create([
            'external_id' => $externalId,
            'product_id' => $product->id,
            'name' => $product->name,
            'source_category' => 'Knjiga',
            'source_url' => 'https://delfi.rs/' . $externalId,
            'price_rsd' => 1000,
            'availability' => $availability,
            'source_hash' => hash('sha256', $externalId),
            'feed_token' => (string) Str::uuid(),
            'is_current' => $current,
            'check_status' => 'matched',
            'imported_at' => $imported ? now() : null,
        ]);
    }
}
