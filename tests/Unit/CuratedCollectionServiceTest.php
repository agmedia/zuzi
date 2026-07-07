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
        $cacheKey = \Closure::bind(function (string $suffix): string {
            return $this->cacheKey($suffix);
        }, $service, CuratedCollectionService::class);
        $featuredLimit = (new \ReflectionClass(CuratedCollectionService::class))
            ->getReflectionConstant('HOMEPAGE_FEATURED_PRODUCTS_LIMIT')
            ?->getValue();

        Cache::put($cacheKey('homepage-widget.featured-' . $featuredLimit), ['stale' => true], now()->addHour());
        Cache::put($cacheKey('featured-products.limit-' . $featuredLimit), ['stale' => true], now()->addHour());
        Cache::put($cacheKey('monthly-ranking.quantity'), ['stale' => true], now()->addHour());
        Cache::put($cacheKey('collection.najpopularnije-ovaj-mjesec'), ['stale' => true], now()->addHour());

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

        $this->assertFalse(Cache::has($cacheKey('homepage-widget.featured-' . $featuredLimit)));
        $this->assertFalse(Cache::has($cacheKey('featured-products.limit-' . $featuredLimit)));
        $this->assertFalse(Cache::has($cacheKey('monthly-ranking.quantity')));
        $this->assertFalse(Cache::has($cacheKey('collection.najpopularnije-ovaj-mjesec')));
        $this->assertFileDoesNotExist($snapshotPath);
    }
}
