<?php

namespace Tests\Unit;

use App\Services\Front\CuratedCollectionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CuratedCollectionServiceTest extends TestCase
{
    public function test_clear_homepage_widget_state_forgets_cached_keys_and_snapshot(): void
    {
        $service = app(CuratedCollectionService::class);
        $prefix = 'curated-collections.v6.' . now()->format('Y-m') . '.';
        $featuredLimit = (new \ReflectionClass(CuratedCollectionService::class))
            ->getReflectionConstant('HOMEPAGE_FEATURED_PRODUCTS_LIMIT')
            ?->getValue();

        Cache::put($prefix . 'homepage-widget.featured-' . $featuredLimit, ['stale' => true], now()->addHour());
        Cache::put($prefix . 'featured-products.limit-' . $featuredLimit, ['stale' => true], now()->addHour());
        Cache::put($prefix . 'monthly-ranking.quantity', ['stale' => true], now()->addHour());
        Cache::put($prefix . 'collection.najpopularnije-ovaj-mjesec', ['stale' => true], now()->addHour());

        $snapshotPath = storage_path('app/curated-homepage-widget.json');
        File::ensureDirectoryExists(dirname($snapshotPath));
        File::put($snapshotPath, json_encode([
            'collections' => [
                'najpopularnije-ovaj-mjesec' => [
                    'count' => 5,
                ],
            ],
        ], JSON_PRETTY_PRINT));

        $service->clearHomepageWidgetState();

        $this->assertFalse(Cache::has($prefix . 'homepage-widget.featured-' . $featuredLimit));
        $this->assertFalse(Cache::has($prefix . 'featured-products.limit-' . $featuredLimit));
        $this->assertFalse(Cache::has($prefix . 'monthly-ranking.quantity'));
        $this->assertFalse(Cache::has($prefix . 'collection.najpopularnije-ovaj-mjesec'));
        $this->assertFileDoesNotExist($snapshotPath);
    }
}
