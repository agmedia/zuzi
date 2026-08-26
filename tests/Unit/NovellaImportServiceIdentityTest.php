<?php

namespace Tests\Unit;

use App\Models\Back\Catalog\NovellaImportProduct;
use App\Services\Novella\NovellaImportService;
use App\Services\Novella\NovellaTerminalException;
use ReflectionClass;
use Tests\TestCase;

class NovellaImportServiceIdentityTest extends TestCase
{
    public function test_it_accepts_the_same_detail_identity_with_normalized_url(): void
    {
        $this->invokeIdentityCheck([
            'remote_product_id' => 123,
            'external_id' => '123',
            'source_url' => 'https://novella.hr/proizvod/knjiga',
        ]);

        $this->assertTrue(true);
    }

    public function test_it_rejects_detail_payload_for_another_product(): void
    {
        $this->expectException(NovellaTerminalException::class);

        $this->invokeIdentityCheck([
            'remote_product_id' => 999,
            'external_id' => '999',
            'source_url' => 'https://novella.hr/proizvod/druga-knjiga/',
        ]);
    }

    private function invokeIdentityCheck(array $details): void
    {
        $reflection = new ReflectionClass(NovellaImportService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('assertDetailIdentity');
        $method->setAccessible(true);
        $source = new NovellaImportProduct([
            'remote_product_id' => 123,
            'external_id' => '123',
            'source_url' => 'https://novella.hr/proizvod/knjiga/',
        ]);

        $method->invoke($service, $source, $details);
    }
}
