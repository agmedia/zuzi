<?php

namespace Tests\Unit;

use App\Services\Laguna\LagunaTranslationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LagunaTranslationServiceTest extends TestCase
{
    public function test_it_translates_from_serbian_to_croatian(): void
    {
        Http::fake(function ($request) {
            $this->assertStringContainsString('sl=sr', $request->url());
            $this->assertStringContainsString('tl=hr', $request->url());

            return Http::response([[['Ovo je hrvatski opis.', 'Ovo je srpski opis.', null, null]], null, 'sr']);
        });

        $translated = app(LagunaTranslationService::class)->translateDescription('Ovo je srpski opis.');

        $this->assertSame('Ovo je hrvatski opis.', $translated);
    }
}
