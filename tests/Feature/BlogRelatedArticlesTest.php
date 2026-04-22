<?php

namespace Tests\Feature;

use App\Http\Controllers\Back\Marketing\BlogController as AdminBlogController;
use App\Http\Controllers\Front\CatalogRouteController;
use App\Models\Back\Marketing\Blog as AdminBlog;
use App\Models\Front\Blog as FrontBlog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BlogRelatedArticlesTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_model_persists_selected_related_product_ids(): void
    {
        $firstRelatedId = $this->createProduct('Prvi povezani artikl', 'BLOG-REL-1');
        $secondRelatedId = $this->createProduct('Drugi povezani artikl', 'BLOG-REL-2');

        $request = Request::create('/admin/marketing/blog', 'POST', [
            'title' => 'Glavni clanak',
            'related_products' => [$firstRelatedId, $secondRelatedId, $firstRelatedId],
            'status' => 'on',
        ]);

        $stored = (new AdminBlog())->validateRequest($request)->create();

        $this->assertInstanceOf(AdminBlog::class, $stored);
        $this->assertSame([$firstRelatedId, $secondRelatedId], $stored->fresh()->related_products);
    }

    public function test_blog_model_supports_legacy_action_list_payload_for_related_products(): void
    {
        $firstRelatedId = $this->createProduct('Legacy povezani artikl 1', 'BLOG-LEG-1');
        $secondRelatedId = $this->createProduct('Legacy povezani artikl 2', 'BLOG-LEG-2');

        $request = Request::create('/admin/marketing/blog', 'POST', [
            'title' => 'Glavni clanak legacy',
            'action_list' => [$firstRelatedId, $secondRelatedId, $firstRelatedId],
            'action_group' => 'product',
            'status' => 'on',
        ]);

        $stored = (new AdminBlog())->validateRequest($request)->create();

        $this->assertInstanceOf(AdminBlog::class, $stored);
        $this->assertSame([$firstRelatedId, $secondRelatedId], $stored->fresh()->related_products);
    }

    public function test_front_blog_detail_resolves_only_active_related_products_in_saved_order(): void
    {
        $firstRelatedId = $this->createProduct('Prvi aktivni artikl', 'BLOG-REL-3');
        $inactiveRelatedId = $this->createProduct('Neaktivan artikl', 'BLOG-REL-4', ['status' => 0]);
        $secondRelatedId = $this->createProduct('Drugi aktivni artikl', 'BLOG-REL-5');
        $outOfStockId = $this->createProduct('Bez zalihe', 'BLOG-REL-6', ['quantity' => 0]);
        $mainBlogId = $this->createBlogPage('Glavni clanak', 'glavni-clanak', [
            'related_products' => json_encode([$inactiveRelatedId, $secondRelatedId, $outOfStockId, $firstRelatedId]),
        ]);

        $blog = FrontBlog::query()->findOrFail($mainBlogId);
        $response = app(CatalogRouteController::class)->blog($blog);
        $relatedProducts = $response->getData()['relatedProducts'];

        $this->assertSame([$secondRelatedId, $firstRelatedId], $relatedProducts->pluck('id')->all());
    }

    public function test_admin_blog_index_orders_posts_from_newest_to_oldest(): void
    {
        $oldestId = $this->createBlogPage('Najstariji', 'najstariji', [
            'created_at' => Carbon::parse('2026-04-01 10:00:00'),
            'updated_at' => Carbon::parse('2026-04-01 10:00:00'),
            'publish_date' => null,
        ]);
        $middleId = $this->createBlogPage('Srednji', 'srednji', [
            'created_at' => Carbon::parse('2026-04-05 10:00:00'),
            'updated_at' => Carbon::parse('2026-04-05 10:00:00'),
            'publish_date' => null,
        ]);
        $newestId = $this->createBlogPage('Najnoviji', 'najnoviji', [
            'created_at' => Carbon::parse('2026-04-02 10:00:00'),
            'updated_at' => Carbon::parse('2026-04-02 10:00:00'),
            'publish_date' => Carbon::parse('2026-04-10 10:00:00'),
        ]);

        $response = app(AdminBlogController::class)->index(Request::create('/admin/marketing/blogs', 'GET'));
        $orderedIds = $response->getData()['blogs']->pluck('id')->all();

        $this->assertSame([$newestId, $middleId, $oldestId], $orderedIds);
    }

    private function createBlogPage(string $title, string $slug, array $overrides = []): int
    {
        return (int) DB::table('pages')->insertGetId(array_merge([
            'category_id' => null,
            'group' => 'blog',
            'title' => $title,
            'short_description' => null,
            'description' => null,
            'meta_title' => $title,
            'meta_description' => null,
            'slug' => $slug,
            'keywords' => null,
            'image' => null,
            'publish_date' => Carbon::now(),
            'viewed' => 0,
            'featured' => 0,
            'related_products' => null,
            'status' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ], $overrides));
    }

    private function createProduct(string $name, string $sku, array $overrides = []): int
    {
        $slug = Str::slug($name . '-' . $sku);

        return (int) DB::table('products')->insertGetId(array_merge([
            'author_id' => 0,
            'publisher_id' => 0,
            'action_id' => 0,
            'name' => $name,
            'sku' => $sku,
            'ean' => null,
            'description' => null,
            'slug' => $slug,
            'url' => '/proizvod/' . $slug,
            'image' => null,
            'price' => 10,
            'quantity' => 5,
            'tax_id' => 1,
            'special' => null,
            'special_from' => null,
            'special_to' => null,
            'meta_title' => $name,
            'meta_description' => null,
            'related_products' => null,
            'pages' => null,
            'dimensions' => null,
            'origin' => null,
            'letter' => null,
            'condition' => null,
            'binding' => null,
            'year' => null,
            'viewed' => 0,
            'sort_order' => 0,
            'push' => 0,
            'status' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ], $overrides));
    }
}
