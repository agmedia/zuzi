<?php

namespace Tests\Feature;

use Tests\TestCase;

class SearchAutocompleteSafetyTest extends TestCase
{
    public function test_autocomplete_short_queries_return_empty_payload(): void
    {
        $response = $this->getJson('/api/v2/pretrazi/autocomplete?pojam_api=ab');

        $response
            ->assertOk()
            ->assertHeader('X-Total-Count', 0)
            ->assertExactJson([
                'counts' => [
                    'products' => 0,
                    'authors' => 0,
                    'categories' => 0,
                ],
                'products' => [],
                'categories' => [],
                'authors' => [],
                'meta' => [],
            ]);
    }
}
