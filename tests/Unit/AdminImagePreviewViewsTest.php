<?php

namespace Tests\Unit;

use Tests\TestCase;

class AdminImagePreviewViewsTest extends TestCase
{
    /** @dataProvider imagePreviewViews */
    public function test_admin_image_preview_views_compile_to_valid_php(string $view): void
    {
        $compiled = app('blade.compiler')->compileString(
            file_get_contents(resource_path('views/' . $view . '.blade.php'))
        );

        // Parse the complete template: an inline @php before a later @endphp
        // can otherwise consume the intervening HTML in this Blade version.
        $this->assertNotEmpty(token_get_all($compiled, TOKEN_PARSE));
    }

    public static function imagePreviewViews(): array
    {
        return [
            'product gallery' => ['back/catalog/product/edit-photos'],
            'product list' => ['back/catalog/product/index'],
            'original product gallery' => ['back/catalog/product/org-edit-photos'],
            'wishlist layout' => ['back/layouts/wishlist/index'],
            'marketing wishlists' => ['back/marketing/wishlist/index'],
            'order details' => ['back/order/show'],
            'history details' => ['back/settings/history/show'],
        ];
    }
}
