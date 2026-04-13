<?php

namespace Tests\Feature;

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
}
