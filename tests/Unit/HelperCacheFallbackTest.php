<?php

namespace Tests\Unit;

use App\Helpers\Helper;
use Tests\TestCase;

class HelperCacheFallbackTest extends TestCase
{
    public function test_remember_using_store_returns_callback_value_when_cache_store_fails(): void
    {
        $store = new class {
            public function get(string $key, mixed $default = null): mixed
            {
                throw new \RuntimeException('MISCONF Redis write failure');
            }

            public function put(string $key, mixed $value, mixed $ttl): bool
            {
                throw new \RuntimeException('MISCONF Redis write failure');
            }
        };

        $callbackCalls = 0;

        $value = Helper::rememberUsingStore($store, 'products.key', now()->addMinute(), function () use (&$callbackCalls) {
            $callbackCalls++;

            return ['cached' => false];
        });

        $this->assertSame(['cached' => false], $value);
        $this->assertSame(1, $callbackCalls);
    }

    public function test_has_using_store_returns_false_when_read_throws(): void
    {
        $store = new class {
            public function has(string $key): bool
            {
                throw new \RuntimeException('Redis unavailable');
            }
        };

        $this->assertFalse(Helper::hasUsingStore($store, 'ga4.purchase.sent.10'));
    }

    public function test_add_using_store_uses_fallback_value_when_write_throws(): void
    {
        $store = new class {
            public function add(string $key, mixed $value, mixed $ttl): bool
            {
                throw new \RuntimeException('MISCONF Redis write failure');
            }
        };

        $this->assertFalse(Helper::addUsingStore($store, 'ga4.purchase.pending.10', true, now()->addMinute()));
        $this->assertTrue(Helper::addUsingStore($store, 'ga4.purchase.pending.10', true, now()->addMinute(), true));
    }

    public function test_put_using_store_returns_false_when_write_throws(): void
    {
        $store = new class {
            public function put(string $key, mixed $value, mixed $ttl): bool
            {
                throw new \RuntimeException('MISCONF Redis write failure');
            }
        };

        $this->assertFalse(Helper::putUsingStore($store, 'ga4.purchase.sent.10', true, now()->addDay()));
    }
}
