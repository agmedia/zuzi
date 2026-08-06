<?php

namespace Tests\Feature;

use App\Http\Livewire\Back\Layout\Search\PublisherSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PublisherSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_publisher_is_selected_instead_of_created_again(): void
    {
        $publisherId = $this->createPublisher('Novela media');

        Livewire::test(PublisherSearch::class)
            ->set('new.title', '  NOVELA MEDIA  ')
            ->call('makeNewPublisher')
            ->assertSet('publisher_id', $publisherId)
            ->assertSet('search', 'Novela media')
            ->assertSet('show_add_window', false)
            ->assertSet('new.title', '');

        $this->assertDatabaseCount('publishers', 1);
    }

    private function createPublisher(string $title): int
    {
        return (int) DB::table('publishers')->insertGetId([
            'letter' => 'N',
            'title' => $title,
            'description' => '',
            'meta_title' => $title,
            'meta_description' => '',
            'lang' => 'hr',
            'sort_order' => 0,
            'status' => 1,
            'slug' => 'novela-media',
            'url' => 'nakladnik/novela-media',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }
}
