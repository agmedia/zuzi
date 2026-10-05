<?php

namespace Tests\Unit;

use App\Models\Back\Catalog\Publisher as BackPublisher;
use App\Models\Front\Catalog\Publisher as FrontPublisher;
use Tests\TestCase;

class PublisherDeliveryWindowTest extends TestCase
{
    /**
     * @dataProvider publisherSlugsWithTwentyDayDelivery
     */
    public function test_it_identifies_publishers_with_a_twenty_day_delivery_window(string $slug, string $title): void
    {
        $publisher = new FrontPublisher([
            'slug' => $slug,
            'title' => $title,
        ]);

        $this->assertTrue($publisher->usesTwentyDayDeliveryWindow());
    }

    public function publisherSlugsWithTwentyDayDelivery(): array
    {
        return [
            'Delfi' => ['delfi', 'Delfi'],
            'Laguna' => ['laguna', 'Laguna'],
            'Laguna d.o.o.' => ['laguna-doo', 'Laguna d.o.o.'],
        ];
    }

    public function test_it_does_not_match_other_publishers_with_similar_names(): void
    {
        $publisher = new FrontPublisher([
            'slug' => 'delfin',
            'title' => 'DELFIN',
        ]);

        $this->assertFalse($publisher->usesTwentyDayDeliveryWindow());
    }

    public function test_it_falls_back_to_the_publisher_title_when_the_slug_is_missing(): void
    {
        $publisher = new FrontPublisher([
            'title' => 'Laguna d.o.o.',
        ]);

        $this->assertTrue($publisher->usesTwentyDayDeliveryWindow());
    }

    public function test_back_office_publisher_uses_the_same_delivery_window_rules(): void
    {
        $publisher = new BackPublisher([
            'slug' => 'delfi',
            'title' => 'Delfi',
        ]);

        $this->assertTrue($publisher->usesTwentyDayDeliveryWindow());
    }
}
