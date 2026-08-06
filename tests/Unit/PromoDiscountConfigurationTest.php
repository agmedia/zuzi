<?php

namespace Tests\Unit;

use App\Services\AccountNoticeMailService;
use App\Services\ReviewRequestPromoService;
use App\Services\UnfinishedOrderPromoService;
use Tests\TestCase;

class PromoDiscountConfigurationTest extends TestCase
{
    public function test_hvala_twenty_is_paused_and_completed_promo_defaults_to_ten(): void
    {
        $service = app(UnfinishedOrderPromoService::class);

        $this->assertSame(10, UnfinishedOrderPromoService::DEFAULT_DISCOUNT);
        $this->assertTrue($service->isAllowedDiscount(10));
        $this->assertFalse($service->isAllowedDiscount(20));
        $this->assertContains(20, UnfinishedOrderPromoService::REPORTABLE_DISCOUNTS);

        $mailService = app(AccountNoticeMailService::class);
        $this->assertSame(
            'Slanje HVALA20 promo mailova je privremeno onemogućeno.',
            $mailService->sendingBlockReason(['coupon_code' => 'HVALA20'])
        );
        $this->assertNull($mailService->sendingBlockReason(['coupon_code' => 'HVALA10-TEST']));
    }

    public function test_review_request_discount_is_ten_percent(): void
    {
        $this->assertSame(10, ReviewRequestPromoService::DISCOUNT_PERCENT);
    }
}
