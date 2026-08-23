<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserDetail;
use Bouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InactiveProductPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_product_stays_hidden_from_guests_and_customers(): void
    {
        $product = $this->createInactiveProduct();
        $previewUrl = $product['url'] . '?preview=1';

        $this->get($product['url'])->assertNotFound();
        $this->get($previewUrl)->assertNotFound();

        $rolelessUser = User::factory()->create();
        $this->createUserDetail($rolelessUser, 'admin');
        $this->actingAs($rolelessUser)->get($previewUrl)->assertNotFound();

        $customer = User::factory()->create();
        $this->createUserDetail($customer, 'customer');
        Bouncer::assign('customer')->to($customer);

        $this->actingAs($customer)->get($previewUrl)->assertNotFound();
    }

    public function test_admin_can_preview_inactive_product_without_tracking_a_view(): void
    {
        $product = $this->createInactiveProduct();
        $admin = User::factory()->create();
        $this->createUserDetail($admin, 'admin');
        Bouncer::allow($admin)->everything();

        $this->actingAs($admin)->get($product['url'])->assertNotFound();

        $response = $this->actingAs($admin)->get($product['url'] . '?preview=1');

        $response->assertOk();
        $response->assertSee('Administratorski pregled');
        $response->assertSee($product['name']);
        $response->assertSee('<meta name="robots" content="noindex,nofollow,noarchive">', false);
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame(0, (int) DB::table('products')->where('id', $product['id'])->value('viewed'));
    }

    private function createInactiveProduct(): array
    {
        $categorySlug = 'test-kategorija';
        $productSlug = 'neaktivna-test-knjiga';
        $name = 'Neaktivna test knjiga';
        $categoryId = (int) DB::table('categories')->insertGetId([
            'parent_id' => 0,
            'title' => 'Test kategorija',
            'description' => null,
            'meta_title' => null,
            'meta_description' => null,
            'image' => 'media/test/category.jpg',
            'group' => 'kategorija-proizvoda',
            'lang' => 'hr',
            'sort_order' => 0,
            'status' => 1,
            'slug' => $categorySlug,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productId = (int) DB::table('products')->insertGetId([
            'author_id' => 0,
            'publisher_id' => 0,
            'action_id' => 0,
            'name' => $name,
            'sku' => 'PREVIEW-1',
            'description' => 'Testni opis neaktivne knjige.',
            'slug' => $productSlug,
            'url' => 'kategorija-proizvoda/' . $categorySlug . '/' . $productSlug,
            'image' => 'media/test/product.jpg',
            'price' => 15,
            'quantity' => 1,
            'tax_id' => 1,
            'special' => null,
            'special_from' => null,
            'special_to' => null,
            'special_lock' => 0,
            'meta_title' => $name,
            'meta_description' => null,
            'related_products' => null,
            'viewed' => 0,
            'sort_order' => 0,
            'push' => 0,
            'status' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_category')->insert([
            'product_id' => $productId,
            'category_id' => $categoryId,
        ]);

        return [
            'id' => $productId,
            'name' => $name,
            'url' => route('catalog.route', [
                'group' => 'kategorija-proizvoda',
                'cat' => $categorySlug,
                'subcat' => $productSlug,
            ]),
        ];
    }

    private function createUserDetail(User $user, string $role): void
    {
        UserDetail::query()->create([
            'user_id' => $user->id,
            'fname' => 'Test',
            'lname' => 'Korisnik',
            'role' => $role,
        ]);
    }
}
