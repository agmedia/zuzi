<?php

namespace Tests\Unit;

use App\Services\Delfi\DelfiTranslationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DelfiTranslationServiceTest extends TestCase
{
    public function test_it_auto_detects_the_source_language_and_translates_to_croatian(): void
    {
        Http::fake(function ($request) {
            $this->assertStringContainsString('sl=auto', $request->url());
            $this->assertStringContainsString('tl=hr', $request->url());

            return Http::response([[['Ovo je hrvatski opis.', 'This is an English description.']], null, 'en']);
        });

        $translated = app(DelfiTranslationService::class)->translateDescription('This is an English description.');

        $this->assertSame('Ovo je hrvatski opis.', $translated);
    }

    public function test_it_does_not_call_the_translation_service_for_an_empty_description(): void
    {
        Http::fake();

        $this->assertSame('', app(DelfiTranslationService::class)->translateDescription('   '));
        Http::assertNothingSent();
    }
}
