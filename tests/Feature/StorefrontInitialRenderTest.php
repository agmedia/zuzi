<?php

namespace Tests\Feature;

use App\Services\AccountNoticeService;
use App\Services\GoogleLoginSettingsService;
use App\Support\InterliberOffer;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StorefrontInitialRenderTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_server_rendered_page_and_navigation_are_visible_without_waiting_for_vue(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-01-01 12:00:00', 'Europe/Zagreb'));
        $this->mock(InterliberOffer::class, function ($mock) {
            $mock->shouldReceive('isActive')->andReturn(false);
        });
        $this->mock(AccountNoticeService::class, function ($mock) {
            $mock->shouldReceive('get')->andReturn([]);
        });
        $this->mock(GoogleLoginSettingsService::class, function ($mock) {
            $mock->shouldReceive('enabled')->andReturn(false);
        });

        $response = $this->get('/interliber-popusti');

        $response->assertOk()->assertSeeText('Tvoja sljedeća knjiga.');

        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);

        foreach (['//header', '//main//h1', '//main//footer'] as $selector) {
            $node = $xpath->query($selector)->item(0);
            $this->assertNotNull($node, $selector . ' must be server-rendered.');

            for ($ancestor = $node; $ancestor instanceof \DOMElement; $ancestor = $ancestor->parentNode) {
                $this->assertFalse($ancestor->hasAttribute('v-cloak'), $selector . ' must not wait for Vue.');
                $this->assertFalse($ancestor->hasAttribute('hidden'), $selector . ' must not be hidden.');
                $this->assertStringNotContainsString('v-cloak--hidden', $ancestor->getAttribute('class'));
            }
        }

        $this->assertSame(1, $xpath->query('//*[@id="agapp"]')->length);
        $this->assertSame(1, $xpath->query('//script[contains(@src, "/js/cart.js")]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="agapp"]//cart-nav-icon[@v-cloak]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="agapp"]//filter-view[@v-cloak]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="agapp"]//div[contains(concat(" ", normalize-space(@class), " "), " spinner ")]')->length);

        // Vue component templates are bundled, never printed as uncompiled mustache text in Blade.
        $response->assertDontSee('v-cloak--hidden', false);
        $this->assertStringNotContainsString('{{', $xpath->query('//main')->item(0)->textContent);
    }
}
