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

        Cache::put($prefix . 'homepage-widget', ['stale' => true], now()->addHour());
        Cache::put($prefix . 'featured-products', ['stale' => true], now()->addHour());
        Cache::put($prefix . 'monthly-ranking.quantity', ['stale' => true], now()->addHour());
        Cache::put($prefix . 'collection.najprodavanije-ovaj-mjesec', ['stale' => true], now()->addHour());

        $snapshotPath = storage_path('app/curated-homepage-widget.json');
        File::ensureDirectoryExists(dirname($snapshotPath));
        File::put($snapshotPath, json_encode([
            'collections' => [
                'najprodavanije-ovaj-mjesec' => [
                    'count' => 5,
                ],
            ],
        ], JSON_PRETTY_PRINT));

        $service->clearHomepageWidgetState();

        $this->assertFalse(Cache::has($prefix . 'homepage-widget'));
        $this->assertFalse(Cache::has($prefix . 'featured-products'));
        $this->assertFalse(Cache::has($prefix . 'monthly-ranking.quantity'));
        $this->assertFalse(Cache::has($prefix . 'collection.najprodavanije-ovaj-mjesec'));
        $this->assertFileDoesNotExist($snapshotPath);
    }
}
