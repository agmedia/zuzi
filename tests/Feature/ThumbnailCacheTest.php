<?php

namespace Tests\Feature;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ThumbnailCacheTest extends TestCase
{
    public function test_thumb_cache_returns_placeholder_when_source_is_missing()
    {
        $response = $this->get('/cache/thumb?size=100x100&src=media/img/products/does-not-exist/missing-thumb.webp');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_thumb_cache_returns_placeholder_when_source_is_not_provided()
    {
        $response = $this->get('/cache/thumb');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_thumb_cache_returns_image_when_image_cache_store_throws()
    {
        Cache::extend('failing-imagecache', function () {
            return new Repository(new class extends ArrayStore {
                public function get($key)
                {
                    throw new \RuntimeException('Simulated Redis MISCONF');
                }

                public function put($key, $value, $seconds)
                {
                    throw new \RuntimeException('Simulated Redis MISCONF');
                }
            });
        });

        config([
            'imagecache.cache_driver' => 'failing-imagecache',
        ]);

        $response = $this->get('/cache/thumb?size=100x100&src=media/img/products/does-not-exist/missing-thumb.webp');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
    }
}
