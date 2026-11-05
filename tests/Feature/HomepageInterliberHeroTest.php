<?php

namespace Tests\Feature;

use App\Http\Controllers\Front\HomeController;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HomepageInterliberHeroTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @dataProvider campaignBoundaryProvider
     */
    public function test_campaign_hero_uses_the_zagreb_date_window(string $instant, bool $isActive): void
    {
        config(['app.timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse($instant, 'Europe/Zagreb'));

        $description = '<section>uvod</section>++slider-index++<!--home-sales-widget-->++novo++';
        $expected = $isActive
            ? str_replace(
                '++slider-index++',
                view('front.layouts.partials.home-interliber-hero')->render()
                    . view('front.layouts.partials.home-bookstore-vote-banner')->render(),
                $description
            )
            : $description;

        $this->assertSame($expected, $this->resolveHero($description));
    }

    public static function campaignBoundaryProvider(): array
    {
        return [
            'second before the campaign' => ['2026-10-25 23:59:59', false],
            'campaign starts inclusively' => ['2026-10-26 00:00:00', true],
            'last second of December first' => ['2026-12-01 23:59:59', true],
            'campaign expires on December second' => ['2026-12-02 00:00:00', false],
        ];
    }

    public function test_expired_campaign_restores_the_original_slider_marker(): void
    {
        $description = '++slider-index++<!--home-sales-widget-->++novo++';

        Carbon::setTestNow(Carbon::parse('2026-12-01 23:59:59', 'Europe/Zagreb'));
        $this->assertStringNotContainsString('++slider-index++', $this->resolveHero($description));

        Carbon::setTestNow(Carbon::parse('2026-12-02 00:00:00', 'Europe/Zagreb'));
        $this->assertSame($description, $this->resolveHero($description));
    }

    public function test_active_campaign_preserves_other_widget_markers(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-15 12:00:00', 'Europe/Zagreb'));

        $description = '++before++<!--home-about-widget-->++slider-index++<!--home-sales-widget-->++novo++';
        $resolved = $this->resolveHero($description);

        $this->assertStringStartsWith('++before++<!--home-about-widget-->', $resolved);
        $this->assertStringEndsWith('<!--home-sales-widget-->++novo++', $resolved);
        $this->assertStringNotContainsString('++slider-index++', $resolved);
    }

    public function test_voting_banner_follows_the_hero_as_one_accessible_external_link(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-05 12:00:00', 'Europe/Zagreb'));

        $resolved = $this->resolveHero('++slider-index++<!--home-sales-widget-->++novo++');
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $resolved);
        $xpath = new \DOMXPath($document);
        $banner = $xpath->query('//section[contains(@class, "interliber-hero")]/following-sibling::*[1][self::a and @class="bookstore-vote-banner"]')->item(0);

        $this->assertInstanceOf(\DOMElement::class, $banner);
        $this->assertSame('https://min-kulture.gov.hr/glasaj/', $banner->getAttribute('href'));
        $this->assertSame('_blank', $banner->getAttribute('target'));
        $this->assertSame('noopener noreferrer', $banner->getAttribute('rel'));
        $this->assertSame('bookstore-vote-title', $banner->getAttribute('aria-labelledby'));
        $this->assertSame(0, $xpath->query('.//a | .//button | .//input', $banner)->length);
        $this->assertStringContainsString('Glasaj za ZUZI Shop', $banner->textContent);
        $this->assertStringContainsString('Podrži nas u izboru za najbolju knjižaru u Hrvatskoj 2026.', $banner->textContent);
        $this->assertStringContainsString('Glasanje do 1. prosinca.', $banner->textContent);
        $this->assertStringContainsString('Glasaj sada', $banner->textContent);
        $this->assertStringNotContainsString('ZUZI20', $banner->textContent);
        $this->assertStringNotContainsString('popust', mb_strtolower($banner->textContent));
        $this->assertStringNotContainsString('poklon', mb_strtolower($banner->textContent));
    }

    public function test_voting_banner_is_not_added_without_the_homepage_slider_marker(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-05 12:00:00', 'Europe/Zagreb'));

        $description = '<section>Existing content</section><!--home-sales-widget-->++novo++';

        $this->assertSame($description, $this->resolveHero($description));
    }

    private function resolveHero(string $description): string
    {
        $controller = new HomeController();

        return \Closure::bind(function (string $description): string {
            return $this->resolveHomepageHero($description);
        }, $controller, HomeController::class)($description);
    }
}
