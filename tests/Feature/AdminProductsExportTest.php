<?php

namespace Tests\Feature;

use App\Exports\Back\Catalog\AdminProductsExport;
use App\Models\Back\Catalog\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminProductsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_products_export_keeps_publisher_filter_and_expected_columns(): void
    {
        $publisherId = $this->createPublisher('Export nakladnik');
        $otherPublisherId = $this->createPublisher('Drugi nakladnik');

        $this->createProduct($publisherId, [
            'name' => 'Knjiga za export',
            'sku' => 'EXP-1',
            'polica' => 'A-12',
            'price' => 24.50,
            'quantity' => 7,
            'itemid' => 123456,
            'isbn' => '9789531234567',
            'status' => 1,
        ]);
        $this->createProduct($otherPublisherId, [
            'name' => 'Tuđa knjiga',
            'sku' => 'EXP-2',
            'itemid' => 987654,
            'isbn' => '9789539876543',
        ]);

        $request = Request::create('/admin/catalog/products/export', 'GET', [
            'publisher' => $publisherId,
        ]);
        $query = (new Product())->filter($request)->select([
            'name',
            'sku',
            'polica',
            'price',
            'quantity',
            'itemid',
            'isbn',
            'status',
        ]);
        $export = new AdminProductsExport($query);

        $this->assertSame([
            'Naziv',
            'Šifra',
            'Polica',
            'Cijena',
            'Količina',
            'ItemID',
            'ISBN',
            'Status',
        ], $export->headings());

        $rows = $export->query()->get()->map(function ($product) use ($export) {
            return $export->map($product);
        })->all();

        $this->assertSame([[
            'Knjiga za export',
            'EXP-1',
            'A-12',
            24.5,
            7,
            '123456',
            '9789531234567',
            'Aktivan',
        ]], $rows);
    }

    public function test_admin_products_export_uses_chunked_queries(): void
    {
        $export = new AdminProductsExport(Product::query());

        $this->assertSame(1000, $export->chunkSize());
    }

    private function createPublisher(string $title): int
    {
        return (int) DB::table('publishers')->insertGetId([
            'letter' => 'E',
            'title' => $title,
            'description' => null,
            'meta_title' => $title,
            'meta_description' => $title,
            'image' => 'media/avatars/avatar0.jpg',
            'lang' => 'hr',
            'sort_order' => 0,
            'status' => 1,
            'slug' => Str::slug($title),
            'url' => '/nakladnik/' . Str::slug($title),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createProduct(int $publisherId, array $overrides = []): int
    {
        $name = $overrides['name'] ?? 'Test knjiga';
        $sku = $overrides['sku'] ?? 'TEST-1';

        return (int) DB::table('products')->insertGetId(array_merge([
            'author_id' => 0,
            'publisher_id' => $publisherId,
            'action_id' => 0,
            'name' => $name,
            'sku' => $sku,
            'polica' => null,
            'ean' => null,
            'isbn' => null,
            'itemid' => null,
            'description' => null,
            'slug' => Str::slug($name . '-' . $sku),
            'url' => '/proizvod/' . Str::slug($name . '-' . $sku),
            'image' => null,
            'price' => 10,
            'quantity' => 0,
            'tax_id' => 1,
            'special' => null,
            'special_from' => null,
            'special_to' => null,
            'special_lock' => 0,
            'meta_title' => $name,
            'meta_description' => $name,
            'related_products' => null,
            'pages' => null,
            'dimensions' => null,
            'origin' => null,
            'letter' => null,
            'language' => null,
            'condition' => null,
            'binding' => null,
            'year' => null,
            'viewed' => 0,
            'sort_order' => 0,
            'push' => 0,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
