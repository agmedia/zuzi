<?php

namespace Tests\Feature;

use App\Http\Controllers\Front\AiDiscoveryController;
use App\Http\Controllers\Front\InterliberPromotionController;
use App\Support\InterliberOffer;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class InterliberPromotionTest extends TestCase
{
    private string $layoutDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        // Exercise the real HTTP route and page without the shared storefront's DB-backed widgets.
        $this->layoutDirectory = sys_get_temp_dir() . '/zuzi-interliber-test-' . bin2hex(random_bytes(6));
        mkdir($this->layoutDirectory . '/front/layouts', 0777, true);
        file_put_contents($this->layoutDirectory . '/front/layouts/app.blade.php', <<<'BLADE'
<!doctype html><html lang="hr"><head><title>@yield('title')</title>
<meta name="description" content="@yield('description')">
<link rel="canonical" href="@yield('canonical')">
@stack('css_after')@stack('meta_tags')</head><body>@yield('content')@stack('js_after')</body></html>
BLADE
        );
        View::getFinder()->prependLocation($this->layoutDirectory);
        View::getFinder()->flush();
    }

    protected function tearDown(): void
    {
        if (isset($this->layoutDirectory)) {
            @unlink($this->layoutDirectory . '/front/layouts/app.blade.php');
            @rmdir($this->layoutDirectory . '/front/layouts');
            @rmdir($this->layoutDirectory . '/front');
            @rmdir($this->layoutDirectory);
        }

        parent::tearDown();
    }

    public function test_explicit_routes_do_not_fall_through_to_the_catalog_resolver(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertSame(InterliberPromotionController::class . '@index', $routes->getByName('interliber.popusti')->getActionName());
        $this->assertSame(AiDiscoveryController::class . '@llms', $routes->getByName('ai.llms')->getActionName());
    }

    public function test_active_offer_is_rendered_in_html_with_matching_faq_schema_and_clean_canonical(): void
    {
        $this->bindOffer(true);

        $response = $this->get('/interliber-popusti?utm_source=google');

        $response->assertOk()->assertViewIs('front.interliber-popusti')->assertViewHas('offerActive', true);
        $response->assertSeeText('20 % popusta na sve')->assertSeeText('ZUZI20');
        $response->assertSee('href="' . route('interliber.popusti') . '"', false);
        $response->assertSee('interliber-popusti.css', false);
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));

        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);
        $json = $xpath->query('//script[@type="application/ld+json"]')->item(0)->textContent;
        $schema = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('WebPage', $schema['@graph'][0]['@type']);
        $this->assertSame('FAQPage', $schema['@graph'][1]['@type']);
        $this->assertCount(4, $schema['@graph'][1]['mainEntity']);
        foreach ($schema['@graph'][1]['mainEntity'] as $question) {
            $response->assertSeeText($question['name']);
            $response->assertSeeText($question['acceptedAnswer']['text']);
        }

        $response->assertDontSee('validThrough', false)->assertDontSee('priceValidUntil', false);
    }

    public function test_inactive_offer_has_no_coupon_or_discount_promise_but_keeps_useful_links(): void
    {
        $this->bindOffer(false);

        $response = $this->get('/interliber-popusti');

        $response->assertOk()->assertViewHas('offerActive', false);
        $response->assertSeeText('Interliber kupon trenutačno nije dostupan.');
        $response->assertDontSee('ZUZI20', false)->assertDontSee('20 %', false);
        foreach (['izdvojeno/najprodavanije-ovaj-mjesec', 'kategorija-proizvoda/beletristika', 'savjeti-za-poklone', 'kategorija-proizvoda'] as $path) {
            $response->assertSee('href="' . url($path) . '"', false);
        }
    }

    private function bindOffer(bool $active): void
    {
        $this->mock(InterliberOffer::class, function ($mock) use ($active) {
            $mock->shouldReceive('isActive')->once()->andReturn($active);
        });
    }
}
