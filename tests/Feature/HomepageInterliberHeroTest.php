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
            ? str_replace('++slider-index++', view('front.layouts.partials.home-interliber-hero')->render(), $description)
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

    private function resolveHero(string $description): string
    {
        $controller = new HomeController();

        return \Closure::bind(function (string $description): string {
            return $this->resolveHomepageHero($description);
        }, $controller, HomeController::class)($description);
    }
}
