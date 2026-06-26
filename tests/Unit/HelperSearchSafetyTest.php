<?php

namespace Tests\Unit;

use App\Helpers\Helper;
use ReflectionMethod;
use Tests\TestCase;

class HelperSearchSafetyTest extends TestCase
{
    public function test_search_collation_uses_configured_value(): void
    {
        config([
            'settings.search_collation' => 'utf8mb4_0900_ai_ci',
            'database.default' => 'mysql',
            'database.connections.mysql.collation' => 'utf8mb4_unicode_ci',
        ]);

        $this->assertSame('utf8mb4_0900_ai_ci', $this->callHelperMethod('searchCollation'));
    }

    public function test_search_collation_rejects_unsafe_values(): void
    {
        config([
            'settings.search_collation' => 'utf8mb4_unicode_ci; DROP TABLE products',
            'database.default' => 'mysql',
            'database.connections.mysql.collation' => 'utf8mb4_unicode_ci',
        ]);

        $this->assertSame('utf8mb4_unicode_ci', $this->callHelperMethod('searchCollation'));
    }

    public function test_fuzzy_author_search_skips_non_latin_and_extreme_lengths(): void
    {
        $this->assertFalse($this->callHelperMethod('shouldRunFuzzyAuthorSearch', 'よどばい'));
        $this->assertFalse($this->callHelperMethod('shouldRunFuzzyAuthorSearch', 'ivo'));
        $this->assertFalse($this->callHelperMethod('shouldRunFuzzyAuthorSearch', str_repeat('a', 81)));
        $this->assertTrue($this->callHelperMethod('shouldRunFuzzyAuthorSearch', 'krleza'));
    }

    private function callHelperMethod(string $method, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionMethod(Helper::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(null, ...$arguments);
    }
}
