<?php

namespace App\Helpers;

function config(string $key)
{
    return match ($key) {
        'settings.order.status.new' => 1,
        'settings.order.status.canceled' => 5,
        'settings.order.status.declined' => 7,
        'settings.order.status.unfinished' => 8,
        'settings.order.status.ready' => 10,
        'settings.order.canceled_status' => [7, 5],
        default => null,
    };
}

namespace Tests\Unit;

use App\Helpers\OrderHelper;
use PHPUnit\Framework\TestCase;

class OrderHelperStatusTransitionTest extends TestCase
{
    /**
     * @dataProvider stockTransitionProvider
     */
    public function test_it_returns_stock_only_for_valid_canceled_transitions(int $fromStatus, int $toStatus, bool $expected): void
    {
        $this->assertSame($expected, OrderHelper::shouldReturnStockOnStatusChange($fromStatus, $toStatus));
    }


    public function stockTransitionProvider(): array
    {
        return [
            'unfinished to canceled does not restore stock' => [8, 5, false],
            'declined to canceled does not restore stock' => [7, 5, false],
            'new to canceled restores stock' => [1, 5, true],
            'ready to declined restores stock' => [10, 7, true],
            'already canceled to canceled does not restore stock again' => [5, 5, false],
        ];
    }
}
