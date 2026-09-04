<?php

namespace Tests\Unit;

use App\Services\Catalog\ImportedProductName;
use PHPUnit\Framework\TestCase;

class ImportedProductNameTest extends TestCase
{
    public function test_it_formats_author_and_book_title(): void
    {
        $this->assertSame(
            'Mihajlo Vasiljević: Sečivo',
            ImportedProductName::format(' Mihajlo  Vasiljević ', 'Sečivo&#x20;')
        );
    }

    public function test_it_does_not_duplicate_an_existing_author_prefix(): void
    {
        $this->assertSame(
            'Mihajlo Vasiljević: Sečivo',
            ImportedProductName::format('Mihajlo Vasiljević', 'mihajlo vasiljević: Sečivo')
        );
    }

    public function test_it_keeps_a_title_when_author_is_missing(): void
    {
        $this->assertSame('Sečivo', ImportedProductName::format(null, ' Sečivo '));
    }
}
