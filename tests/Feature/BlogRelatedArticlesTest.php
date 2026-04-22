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

    public function test_blog_model_edit_normalizes_publish_date_and_related_products_json_fallback(): void
    {
        $firstRelatedId = $this->createProduct('Edit povezani artikl 1', 'BLOG-EDIT-1');
        $secondRelatedId = $this->createProduct('Edit povezani artikl 2', 'BLOG-EDIT-2');
        $blogId = $this->createBlogPage('Edit glavni clanak', 'edit-glavni-clanak');

        $blog = AdminBlog::query()->findOrFail($blogId);
        $request = Request::create('/admin/marketing/blog/' . $blogId, 'PATCH', [
            'title' => 'Edit glavni clanak',
            'publish_date' => '22.04.2026 14:30',
            'related_products_json' => json_encode([$firstRelatedId, $secondRelatedId]),
            'status' => 'on',
        ]);

        $updated = $blog->validateRequest($request)->edit();

        $this->assertInstanceOf(AdminBlog::class, $updated);
        $this->assertSame([$firstRelatedId, $secondRelatedId], $updated->fresh()->related_products);
        $this->assertSame('2026-04-22 14:30', $updated->fresh()->publish_date->format('Y-m-d H:i'));
    }

    public function test_blog_model_persists_cta_blocks_and_buttons(): void
    {
        $request = Request::create('/admin/marketing/blog', 'POST', [
            'title' => 'CTA glavni clanak',
            'status' => 'on',
            'cta_blocks' => [
                [
                    'title' => 'Istakni kategorije',
                    'description' => 'Odaberi podkategoriju koja te zanima.',
                    'sort_order' => 2,
                    'is_active' => 1,
                    'buttons' => [
                        [
                            'label' => 'Moderni ljubavni romani',
                            'url' => '/beletristika/ljubici/moderni',
                            'icon' => '💕',
                            'style' => 'outline',
                            'sort_order' => 2,
                            'is_active' => 1,
                        ],
                        [
                            'label' => 'Povijesni ljubici',
                            'url' => '/beletristika/ljubici/povijesni',
                            'icon' => '📖',
                            'style' => 'secondary',
                            'sort_order' => 1,
                            'is_active' => 0,
                        ],
                    ],
                ],
                [
                    'title' => 'Preporucene kategorije',
                    'description' => null,
                    'sort_order' => 1,
                    'is_active' => 0,
                    'buttons' => [
                        [
                            'label' => 'Strastveni romani',
                            'url' => '/beletristika/ljubici/strastveni',
                            'icon' => '🔥',
                            'style' => 'primary',
                            'sort_order' => 1,
                            'is_active' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        $stored = (new AdminBlog())->validateRequest($request)->create();

        $this->assertInstanceOf(AdminBlog::class, $stored);

        $blocks = $stored->fresh()->ctaBlocks()->with('buttons')->get();

        $this->assertCount(2, $blocks);
        $this->assertSame(['Preporucene kategorije', 'Istakni kategorije'], $blocks->pluck('title')->all());
        $this->assertFalse($blocks->first()->is_active);
        $this->assertSame(['Povijesni ljubici', 'Moderni ljubavni romani'], $blocks->last()->buttons->pluck('label')->all());
        $this->assertFalse($blocks->last()->buttons->first()->is_active);
        $this->assertSame('secondary', $blocks->last()->buttons->first()->style);
    }

    public function test_blog_model_edit_replaces_cta_blocks_and_reports_self_link_warning(): void
    {
        $blogId = $this->createBlogPage('Edit CTA clanak', 'edit-cta-clanak');
        $blockId = $this->createBlogCtaBlock($blogId, [
            'title' => 'Stari CTA',
            'sort_order' => 1,
        ]);
        $buttonId = $this->createBlogCtaButton($blockId, [
            'label' => 'Stari button',
            'url' => '/staro',
        ]);
        $this->createBlogCtaBlock($blogId, [
            'title' => 'Obrisi me',
            'sort_order' => 2,
        ]);

        $blog = AdminBlog::query()->findOrFail($blogId);
        $request = Request::create('/admin/marketing/blog/' . $blogId, 'PATCH', [
            'title' => 'Edit CTA clanak',
            'status' => 'on',
            'cta_blocks' => [
                [
                    'id' => $blockId,
                    'title' => 'Novi CTA',
                    'description' => 'Novi opis',
                    'sort_order' => 1,
                    'is_active' => 1,
                    'buttons' => [
                        [
                            'id' => $buttonId,
                            'label' => 'Procitaj ovaj clanak',
                            'url' => '/blog/edit-cta-clanak',
                            'icon' => '📚',
                            'style' => 'outline',
                            'sort_order' => 1,
                            'is_active' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        $updated = $blog->validateRequest($request)->edit();

        $this->assertInstanceOf(AdminBlog::class, $updated);
        $this->assertSame('CTA buttoni vode na isti blog članak: Procitaj ovaj clanak.', $blog->ctaWarningMessage());
        $this->assertSame(1, DB::table('blog_cta_blocks')->where('blog_post_id', $blogId)->count());
        $this->assertSame(1, DB::table('blog_cta_buttons')->count());
        $this->assertSame('Novi CTA', DB::table('blog_cta_blocks')->where('blog_post_id', $blogId)->value('title'));
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

    public function test_front_blog_detail_supports_legacy_double_encoded_related_products(): void
    {
        $firstRelatedId = $this->createProduct('Legacy front artikl 1', 'BLOG-LEGACY-FRONT-1');
        $secondRelatedId = $this->createProduct('Legacy front artikl 2', 'BLOG-LEGACY-FRONT-2');
        $mainBlogId = $this->createBlogPage('Legacy front clanak', 'legacy-front-clanak', [
            'related_products' => json_encode(json_encode([$firstRelatedId, $secondRelatedId])),
        ]);

        $blog = FrontBlog::query()->findOrFail($mainBlogId);
        $response = app(CatalogRouteController::class)->blog($blog);
        $relatedProducts = $response->getData()['relatedProducts'];

        $this->assertSame([$firstRelatedId, $secondRelatedId], $relatedProducts->pluck('id')->all());
    }

    public function test_front_blog_detail_resolves_only_active_cta_blocks_and_buttons_in_saved_order(): void
    {
        $blogId = $this->createBlogPage('Front CTA clanak', 'front-cta-clanak');
        $firstBlockId = $this->createBlogCtaBlock($blogId, [
            'title' => 'Drugi prikazani blok',
            'sort_order' => 2,
            'is_active' => 1,
        ]);
        $secondBlockId = $this->createBlogCtaBlock($blogId, [
            'title' => 'Prvi prikazani blok',
            'sort_order' => 1,
            'is_active' => 1,
        ]);
        $this->createBlogCtaBlock($blogId, [
            'title' => 'Neaktivan blok',
            'sort_order' => 3,
            'is_active' => 0,
        ]);

        $this->createBlogCtaButton($firstBlockId, [
            'label' => 'Neaktivan button',
            'url' => '/neaktivan',
            'sort_order' => 1,
            'is_active' => 0,
        ]);
        $this->createBlogCtaButton($firstBlockId, [
            'label' => 'Drugi aktivni button',
            'url' => '/drugi-aktivni',
            'sort_order' => 2,
            'is_active' => 1,
        ]);
        $this->createBlogCtaButton($secondBlockId, [
            'label' => 'Prvi aktivni button',
            'url' => '/prvi-aktivni',
            'sort_order' => 1,
            'is_active' => 1,
        ]);

        $blog = FrontBlog::query()->findOrFail($blogId);
        $response = app(CatalogRouteController::class)->blog($blog);
        $ctaBlocks = $response->getData()['ctaBlocks'];

        $this->assertSame(['Prvi prikazani blok', 'Drugi prikazani blok'], $ctaBlocks->pluck('title')->all());
        $this->assertSame(['Prvi aktivni button'], $ctaBlocks->first()->buttons->pluck('label')->all());
        $this->assertSame(['Drugi aktivni button'], $ctaBlocks->last()->buttons->pluck('label')->all());
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

    public function test_admin_blog_edit_exposes_reusable_cta_block_library(): void
    {
        $currentBlogId = $this->createBlogPage('Aktivni clanak', 'aktivni-clanak');
        $otherBlogId = $this->createBlogPage('Izvorni CTA clanak', 'izvorni-cta-clanak');

        $this->createBlogCtaBlock($currentBlogId, [
            'title' => 'Postojeci blok trenutnog clanka',
            'sort_order' => 1,
            'is_active' => 1,
        ]);

        $firstReusableBlockId = $this->createBlogCtaBlock($otherBlogId, [
            'title' => 'Biblioteka blok A',
            'description' => 'Opis reusable bloka A',
            'sort_order' => 1,
            'is_active' => 1,
        ]);
        $secondReusableBlockId = $this->createBlogCtaBlock($otherBlogId, [
            'title' => 'Biblioteka blok B',
            'description' => null,
            'sort_order' => 2,
            'is_active' => 0,
        ]);

        $this->createBlogCtaButton($firstReusableBlockId, [
            'label' => 'Button A',
            'url' => '/button-a',
            'icon' => '💕',
            'style' => 'outline',
            'sort_order' => 1,
            'is_active' => 1,
        ]);
        $this->createBlogCtaButton($secondReusableBlockId, [
            'label' => 'Button B',
            'url' => '/button-b',
            'icon' => '🔥',
            'style' => 'primary',
            'sort_order' => 1,
            'is_active' => 0,
        ]);

        $response = app(AdminBlogController::class)->edit(AdminBlog::query()->findOrFail($currentBlogId));

        $payload = $response->getData();
        $reusableCtaBlocks = $payload['reusableCtaBlocks'];

        $this->assertCount(2, $reusableCtaBlocks);
        $this->assertSame('Biblioteka blok A', $reusableCtaBlocks[0]['title']);
        $this->assertSame('Button A', $reusableCtaBlocks[0]['buttons'][0]['label']);
        $this->assertSame('Biblioteka blok B', $reusableCtaBlocks[1]['title']);
        $this->assertSame('primary', $reusableCtaBlocks[1]['buttons'][0]['style']);
        $this->assertFalse($reusableCtaBlocks[1]['buttons'][0]['is_active']);
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

    private function createBlogCtaBlock(int $blogId, array $overrides = []): int
    {
        return (int) DB::table('blog_cta_blocks')->insertGetId(array_merge([
            'blog_post_id' => $blogId,
            'title' => 'CTA blok',
            'description' => null,
            'sort_order' => 1,
            'is_active' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ], $overrides));
    }

    private function createBlogCtaButton(int $blockId, array $overrides = []): int
    {
        return (int) DB::table('blog_cta_buttons')->insertGetId(array_merge([
            'cta_block_id' => $blockId,
            'label' => 'CTA button',
            'url' => '/cta-button',
            'icon' => null,
            'style' => 'outline',
            'sort_order' => 1,
            'is_active' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ], $overrides));
    }
}
