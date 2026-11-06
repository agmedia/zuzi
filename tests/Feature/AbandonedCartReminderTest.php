<?php

namespace Tests\Feature;

use App\Mail\UnfinishedOrderReminder;
use App\Models\AbandonedCartReminder;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderHistory;
use App\Services\AbandonedCartReminderService;
use App\Services\UnfinishedOrderPromoService;
use App\Support\InterliberOffer;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class AbandonedCartReminderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('cache.default', 'array');
        config()->set('abandoned_cart', [
            'enabled' => true,
            'starts_at' => '2026-11-06 10:00:00',
            'delays_minutes' => [1 => 60, 2 => 1440],
            'min_gap_minutes' => 1380,
            'max_age_days' => 3,
            'batch_size' => 25,
            'max_reminders' => 2,
            'recovery_link_days' => 7,
            'recipient_cooldown_hours' => 72,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->createSchema();

        Carbon::setTestNow(Carbon::parse('2026-11-06 12:00:00', 'Europe/Zagreb'));
        $this->mock(InterliberOffer::class, function ($mock) {
            $mock->shouldReceive('isActive')->andReturn(true);
        });
        Mail::fake();
    }

    protected function tearDown(): void
    {
        OrderHistory::flushEventListeners();
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_first_reminder_waits_one_hour_after_the_latest_checkout_activity(): void
    {
        $order = $this->createOrder([
            'created_at' => '2026-11-06 10:00:00',
            'unfinished_at' => '2026-11-06 11:30:00',
        ]);
        $service = app(AbandonedCartReminderService::class);

        Carbon::setTestNow('2026-11-06 12:29:59');
        $this->assertSame([], $service->candidatesForSequence(1, 25)->pluck('id')->all());

        Carbon::setTestNow('2026-11-06 12:30:00');
        $this->assertSame([$order->id], $service->candidatesForSequence(1, 25)->pluck('id')->all());
        $this->assertSame('2026-11-06 12:30:00', $service->scheduledFor($order, 1)->format('Y-m-d H:i:s'));
    }

    public function test_first_reminder_sends_selected_books_the_active_code_and_a_recovery_link_once(): void
    {
        $order = $this->createOrder();

        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);
        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);

        Mail::assertSent(UnfinishedOrderReminder::class, 1);
        Mail::assertSent(UnfinishedOrderReminder::class, function (UnfinishedOrderReminder $mail) use ($order) {
            $html = $this->renderReminder($mail);

            return $mail->hasTo('ana@example.com')
                && (int) $mail->order->id === (int) $order->id
                && str_contains($html, 'Odabrana knjiga')
                && str_contains($html, 'ZUZI20')
                && str_contains($html, 'Dovrši kupnju')
                && str_contains($html, 'signature=');
        });
        $this->assertSame(1, DB::table('abandoned_cart_reminders')->count());
        $this->assertNotNull(DB::table('abandoned_cart_reminders')->value('attempted_at'));
        $this->assertNotNull(DB::table('abandoned_cart_reminders')->value('sent_at'));
        $this->assertSame(0, DB::table('product_actions')->count(), 'The public code must not create individual HVALA coupons.');
    }

    public function test_second_reminder_is_sent_the_next_day_and_never_more_than_twice(): void
    {
        $order = $this->createOrder();
        $service = app(AbandonedCartReminderService::class);

        Carbon::setTestNow('2026-11-06 11:00:00');
        $service->send($order, 1);

        Carbon::setTestNow('2026-11-07 09:59:59');
        $this->assertSame([], $service->candidatesForSequence(2, 25)->pluck('id')->all());

        Carbon::setTestNow('2026-11-07 10:00:00');
        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);
        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);

        Mail::assertSent(UnfinishedOrderReminder::class, 2);
        $this->assertSame([1, 2], DB::table('abandoned_cart_reminders')->orderBy('sequence')->pluck('sequence')->all());
        $this->assertNull($service->nextSequence($order->fresh()));
    }

    public function test_late_scheduler_does_not_send_both_reminders_together(): void
    {
        $order = $this->createOrder();
        Carbon::setTestNow('2026-11-07 11:00:00');

        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);
        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);
        Mail::assertSent(UnfinishedOrderReminder::class, 1);

        Carbon::setTestNow('2026-11-08 09:59:59');
        $this->assertSame([], app(AbandonedCartReminderService::class)->candidatesForSequence(2, 25)->pluck('id')->all());

        Carbon::setTestNow('2026-11-08 10:00:00');
        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);
        Mail::assertSent(UnfinishedOrderReminder::class, 2);
    }

    public function test_a_manual_first_reminder_and_the_automatic_sender_share_the_same_stage(): void
    {
        $order = $this->createOrder();
        $service = app(AbandonedCartReminderService::class);

        Carbon::setTestNow('2026-11-06 10:15:00');
        $service->send($order, 1, AbandonedCartReminder::SOURCE_MANUAL);
        $this->assertSame(2, $service->nextSequence($order->fresh()));

        Carbon::setTestNow('2026-11-06 12:00:00');
        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);
        Mail::assertSent(UnfinishedOrderReminder::class, 1);

        Carbon::setTestNow('2026-11-07 10:00:00');
        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);
        Mail::assertSent(UnfinishedOrderReminder::class, 2);
    }

    public function test_a_second_manual_attempt_cannot_repeat_the_first_reminder(): void
    {
        $order = $this->createOrder();
        $service = app(AbandonedCartReminderService::class);
        $service->send($order, 1, AbandonedCartReminder::SOURCE_MANUAL);

        try {
            $service->send($order, 1, AbandonedCartReminder::SOURCE_MANUAL);
            $this->fail('The same manual stage was accepted twice.');
        } catch (RuntimeException $exception) {
            Mail::assertSent(UnfinishedOrderReminder::class, 1);
            $this->assertSame(1, DB::table('abandoned_cart_reminders')->count());
        }
    }

    public function test_only_the_latest_unfinished_attempt_per_normalized_email_is_selected(): void
    {
        $this->createOrder(['payment_email' => ' ANA@EXAMPLE.COM ', 'created_at' => '2026-11-06 10:00:00']);
        $latest = $this->createOrder(['payment_email' => 'ana@example.com', 'created_at' => '2026-11-06 10:30:00']);

        $this->assertSame([$latest->id], app(AbandonedCartReminderService::class)->candidatesForSequence(1, 25)->pluck('id')->all());
    }

    public function test_only_the_latest_unfinished_attempt_per_registered_customer_is_selected(): void
    {
        $this->createOrder(['user_id' => 45, 'payment_email' => 'old@example.com', 'created_at' => '2026-11-06 10:00:00']);
        $latest = $this->createOrder(['user_id' => 45, 'payment_email' => 'new@example.com', 'created_at' => '2026-11-06 10:30:00']);

        $this->assertSame([$latest->id], app(AbandonedCartReminderService::class)->candidatesForSequence(1, 25)->pluck('id')->all());
    }

    /** @dataProvider repeatedRecipientIdentities */
    public function test_recreating_a_cart_cannot_restart_the_email_sequence_during_recipient_cooldown(array $firstIdentity, array $secondIdentity): void
    {
        $first = $this->createOrder($firstIdentity);
        Carbon::setTestNow('2026-11-06 11:00:00');
        app(AbandonedCartReminderService::class)->send($first, 1);

        $this->createOrder(array_merge($secondIdentity, ['created_at' => '2026-11-06 11:15:00']));
        Carbon::setTestNow('2026-11-06 12:15:00');
        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);

        Mail::assertSent(UnfinishedOrderReminder::class, 1);
        $this->assertSame(1, DB::table('abandoned_cart_reminders')->count());
        $this->assertSame([], app(AbandonedCartReminderService::class)->candidatesForSequence(1, 25)->pluck('id')->all());
    }

    public static function repeatedRecipientIdentities(): array
    {
        return [
            'normalized email' => [['payment_email' => 'ana@example.com'], ['payment_email' => ' ANA@EXAMPLE.COM ']],
            'registered customer changing email' => [
                ['user_id' => 45, 'payment_email' => 'old@example.com'],
                ['user_id' => 45, 'payment_email' => 'new@example.com'],
            ],
        ];
    }

    /** @dataProvider acceptedOrderStatuses */
    public function test_an_accepted_sibling_order_prevents_both_reminders_even_before_payment(int $status): void
    {
        $order = $this->createOrder(['payment_email' => ' Ana@Example.com ']);
        $this->createOrder([
            'order_status_id' => $status,
            'payment_email' => 'ana@example.com',
            'created_at' => '2026-11-06 11:00:00',
        ]);

        $service = app(AbandonedCartReminderService::class);
        $this->assertSame([], $service->candidatesForSequence(1, 25)->pluck('id')->all());
        $this->assertFalse($service->canRecover($order));
        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);
        Mail::assertNothingSent();
    }

    public static function acceptedOrderStatuses(): array
    {
        return [
            'new COD order' => [1],
            'awaiting bank payment' => [2],
            'paid' => [3],
            'sent' => [4],
            'refunded purchase' => [6],
            'completed' => [9],
            'ready for pickup' => [10],
            'processing' => [11],
        ];
    }

    public function test_purchase_by_the_same_registered_customer_also_stops_reminders_after_an_email_change(): void
    {
        $order = $this->createOrder(['user_id' => 45, 'payment_email' => 'old@example.com']);
        $this->createOrder([
            'user_id' => 45,
            'order_status_id' => 1,
            'payment_email' => 'new@example.com',
            'created_at' => '2026-11-06 11:00:00',
        ]);

        $this->assertSame([], app(AbandonedCartReminderService::class)->candidatesForSequence(1, 25)->pluck('id')->all());
        $this->assertFalse(app(AbandonedCartReminderService::class)->canRecover($order));
    }

    public function test_a_purchase_completed_on_an_older_created_order_also_stops_reminders(): void
    {
        $order = $this->createOrder();
        $this->createOrder([
            'order_status_id' => 3,
            'created_at' => '2026-11-06 09:00:00',
            'checkout_processed_at' => '2026-11-06 11:00:00',
        ]);

        $this->assertSame([], app(AbandonedCartReminderService::class)->candidatesForSequence(1, 25)->pluck('id')->all());
        $this->assertFalse(app(AbandonedCartReminderService::class)->canRecover($order));
    }

    public function test_a_purchase_between_reminders_cancels_the_second_message(): void
    {
        $order = $this->createOrder();
        Carbon::setTestNow('2026-11-06 11:00:00');
        app(AbandonedCartReminderService::class)->send($order, 1);
        $this->createOrder(['order_status_id' => 1, 'created_at' => '2026-11-06 14:00:00']);

        Carbon::setTestNow('2026-11-07 10:00:00');
        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);

        Mail::assertSent(UnfinishedOrderReminder::class, 1);
        $this->assertSame([], app(AbandonedCartReminderService::class)->candidatesForSequence(2, 25)->pluck('id')->all());
    }

    public function test_a_recent_purchase_status_in_history_stops_reminders_for_an_older_order(): void
    {
        $this->createOrder();
        $completed = $this->createOrder(['order_status_id' => 1, 'created_at' => '2026-11-06 09:00:00']);
        $this->history($completed, 'Narudžba potvrđena.', 1, '2026-11-06 11:00:00');

        $this->assertSame([], app(AbandonedCartReminderService::class)->candidatesForSequence(1, 25)->pluck('id')->all());
    }

    public function test_an_older_purchase_does_not_block_a_new_abandoned_cart_forever(): void
    {
        $this->createOrder(['order_status_id' => 9, 'created_at' => '2026-11-01 10:00:00']);
        $order = $this->createOrder();

        $this->assertSame([$order->id], app(AbandonedCartReminderService::class)->candidatesForSequence(1, 25)->pluck('id')->all());
    }

    /** @dataProvider processedSourceStates */
    public function test_a_processed_or_paid_source_order_is_never_emailed(array $overrides, bool $successfulTransaction): void
    {
        $order = $this->createOrder($overrides);
        if ($successfulTransaction) {
            DB::table('order_transactions')->insert([
                'order_id' => $order->id, 'success' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);
        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('abandoned_cart_reminders')->count());
    }

    public static function processedSourceStates(): array
    {
        return [
            'accepted status' => [['order_status_id' => 1], false],
            'processed with stale unfinished status' => [['checkout_processed_at' => '2026-11-06 11:00:00'], false],
            'successful transaction with stale unfinished status' => [[], true],
        ];
    }

    public function test_send_refreshes_a_stale_candidate_before_delivering(): void
    {
        $order = $this->createOrder();
        $candidate = app(AbandonedCartReminderService::class)->candidatesForSequence(1, 25)->first();
        DB::table('orders')->where('id', $order->id)->update(['order_status_id' => 1, 'checkout_processed_at' => now()]);

        $this->expectException(RuntimeException::class);
        try {
            app(AbandonedCartReminderService::class)->send($candidate, 1);
        } finally {
            Mail::assertNothingSent();
        }
    }

    public function test_recipient_lock_uses_the_fresh_email_instead_of_a_stale_candidate(): void
    {
        $stale = $this->createOrder(['payment_email' => 'old@example.com']);
        DB::table('orders')->where('id', $stale->id)->update(['payment_email' => 'new@example.com']);
        $lock = Cache::lock('abandoned-cart:email:' . hash('sha256', 'new@example.com'), 120);
        $this->assertTrue($lock->get());

        try {
            app(AbandonedCartReminderService::class)->send($stale, 1);
            $this->fail('The actual recipient is already locked by another sender.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('u tijeku', $exception->getMessage());
            Mail::assertNothingSent();
            $this->assertSame(0, DB::table('abandoned_cart_reminders')->count());
        } finally {
            $lock->release();
        }
    }

    public function test_manual_promotion_is_reserved_before_its_callback_and_blocks_automatic_reminders(): void
    {
        $order = $this->createOrder();
        $callbacks = 0;
        $response = app(AbandonedCartReminderService::class)->sendPromotion($order, function (Order $fresh) use (&$callbacks) {
            $callbacks++;
            $reserved = DB::table('abandoned_cart_reminders')->where('order_id', $fresh->id)->first();
            $this->assertNotNull($reserved);
            $this->assertNotNull($reserved->attempted_at);
            $this->assertNull($reserved->sent_at);
            $this->assertSame(AbandonedCartReminder::SOURCE_MANUAL, $reserved->source);
            $this->recordIssuedPromotion($fresh);

            return new JsonResponse(['message' => 'Promo mail je uspješno poslan.']);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, $callbacks);
        $this->assertNotNull(DB::table('abandoned_cart_reminders')->value('sent_at'));

        Carbon::setTestNow('2026-11-07 12:00:00');
        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);
        Mail::assertNothingSent();
        $this->assertSame(1, DB::table('abandoned_cart_reminders')->count());
    }

    public function test_a_manual_promotion_is_rejected_after_an_automatic_reminder_even_if_history_failed(): void
    {
        $order = $this->createOrder();
        OrderHistory::creating(function () {
            throw new RuntimeException('History failed after delivery.');
        });
        app(AbandonedCartReminderService::class)->send($order, 1);
        OrderHistory::flushEventListeners();
        $this->assertSame(0, DB::table('order_history')->count());
        $callbackInvoked = false;

        try {
            app(AbandonedCartReminderService::class)->sendPromotion($order, function () use (&$callbackInvoked) {
                $callbackInvoked = true;

                return new JsonResponse(['message' => 'Unexpected second message.']);
            });
            $this->fail('A manual promotion must not duplicate the automatic reminder.');
        } catch (RuntimeException $exception) {
            $this->assertFalse($callbackInvoked);
            Mail::assertSent(UnfinishedOrderReminder::class, 1);
            $this->assertSame(1, DB::table('abandoned_cart_reminders')->count());
        }
    }

    public function test_manual_promotion_refreshes_purchase_state_before_running_its_callback(): void
    {
        $stale = $this->createOrder();
        DB::table('orders')->where('id', $stale->id)->update(['order_status_id' => 1, 'checkout_processed_at' => now()]);
        $callbackInvoked = false;

        try {
            app(AbandonedCartReminderService::class)->sendPromotion($stale, function () use (&$callbackInvoked) {
                $callbackInvoked = true;

                return new JsonResponse(['message' => 'Unexpected message after purchase.']);
            });
            $this->fail('The customer completed this order after it was loaded.');
        } catch (RuntimeException $exception) {
            $this->assertFalse($callbackInvoked);
            Mail::assertNothingSent();
            $this->assertSame(0, DB::table('abandoned_cart_reminders')->count());
        }
    }

    public function test_a_failed_manual_promotion_callback_keeps_its_reservation_and_cannot_be_repeated(): void
    {
        $order = $this->createOrder();
        $callbacks = 0;
        $response = app(AbandonedCartReminderService::class)->sendPromotion($order, function (Order $fresh) use (&$callbacks) {
            $callbacks++;
            $this->recordIssuedPromotion($fresh);

            return new JsonResponse(['error' => 'SMTP acceptance could not be confirmed.'], 500);
        });
        $this->assertSame(500, $response->getStatusCode());
        $claim = DB::table('abandoned_cart_reminders')->first();
        $this->assertNotNull($claim->attempted_at);
        $this->assertNull($claim->sent_at);
        $this->assertNotEmpty($claim->last_error);

        try {
            app(AbandonedCartReminderService::class)->sendPromotion($order, function () use (&$callbacks) {
                $callbacks++;

                return new JsonResponse(['message' => 'Unexpected retry.']);
            });
            $this->fail('An uncertain manual delivery must not be retried.');
        } catch (RuntimeException $exception) {
            $this->assertSame(1, $callbacks);
            $this->assertSame(1, DB::table('abandoned_cart_reminders')->count());
        }

        Carbon::setTestNow('2026-11-07 12:00:00');
        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);
        Mail::assertNothingSent();
    }

    /** @dataProvider previousManualMessages */
    public function test_legacy_manual_reminders_and_promotions_are_not_automatically_duplicated(string $kind): void
    {
        $order = $this->createOrder();
        if ($kind === 'reminder') {
            $this->history($order, UnfinishedOrderPromoService::REMINDER_HISTORY_COMMENT);
        } elseif ($kind === 'promo history') {
            $this->history($order, 'Poslan promo email za nedovrsenu narudzbu. Kod: HVALA10-TEST123. Popust: -10%.');
        } else {
            DB::table('product_actions')->insert([
                'title' => UnfinishedOrderPromoService::TITLE_PREFIX . $order->id,
                'group' => 'total', 'coupon' => 'HVALA10-TEST123', 'discount' => 10,
                'type' => 'P', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);
        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('abandoned_cart_reminders')->count());
    }

    public static function previousManualMessages(): array
    {
        return [['reminder'], ['promo history'], ['issued promo']];
    }

    public function test_a_failed_delivery_attempt_is_retained_and_is_not_retried_automatically(): void
    {
        $order = $this->createOrder();
        Mail::shouldReceive('to')->once()->with('ana@example.com')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP connection became uncertain.'));

        try {
            app(AbandonedCartReminderService::class)->send($order, 1);
            $this->fail('A failed delivery should report failure.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('SMTP', $exception->getMessage());
        }

        $claim = DB::table('abandoned_cart_reminders')->first();
        $this->assertNotNull($claim);
        $this->assertNotNull($claim->attempted_at);
        $this->assertNull($claim->sent_at);
        $this->assertStringContainsString('SMTP', (string) $claim->last_error);
        $this->assertSame([], app(AbandonedCartReminderService::class)->candidatesForSequence(1, 25)->pluck('id')->all());
        $this->assertSame([], app(AbandonedCartReminderService::class)->candidatesForSequence(2, 25)->pluck('id')->all());
    }

    public function test_a_history_failure_after_delivery_cannot_make_the_email_send_again(): void
    {
        $order = $this->createOrder();
        $historyAttempted = false;
        OrderHistory::creating(function () use (&$historyAttempted) {
            $historyAttempted = true;
            throw new RuntimeException('History persistence failed after delivery.');
        });

        $sent = app(AbandonedCartReminderService::class)->send($order, 1);
        $this->assertTrue($historyAttempted, 'The test must exercise a failure after successful delivery.');
        $this->assertNotNull($sent->sent_at);
        Mail::assertSent(UnfinishedOrderReminder::class, 1);
        OrderHistory::flushEventListeners();

        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);
        Mail::assertSent(UnfinishedOrderReminder::class, 1);
        $this->assertSame(1, DB::table('abandoned_cart_reminders')->count());
    }

    public function test_old_orders_invalid_addresses_and_empty_orders_are_not_backfilled(): void
    {
        $this->createOrder(['created_at' => '2026-11-06 09:59:59']);
        $this->createOrder(['payment_email' => 'not-an-email']);
        $this->createOrder(['payment_email' => 'empty@example.com'], false);

        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);
        Mail::assertNothingSent();
    }

    public function test_an_order_older_than_the_automatic_reminder_window_is_not_emailed(): void
    {
        $this->createOrder();
        Carbon::setTestNow('2026-11-10 12:00:00');

        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('abandoned_cart_reminders')->count());
    }

    public function test_the_database_activation_marker_excludes_earlier_orders(): void
    {
        config()->set('abandoned_cart.starts_at', null);
        DB::table('settings')->insert([
            'code' => 'order', 'key' => 'abandoned_cart_reminders_started_at',
            'value' => '2026-11-06 11:00:00', 'json' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->createOrder();
        $new = $this->createOrder(['payment_email' => 'new@example.com', 'created_at' => '2026-11-06 11:00:00']);

        $this->assertSame([$new->id], app(AbandonedCartReminderService::class)->candidatesForSequence(1, 25)->pluck('id')->all());
    }

    public function test_no_automatic_messages_are_sent_if_activation_was_not_recorded(): void
    {
        $this->createOrder();
        config()->set('abandoned_cart.starts_at', null);

        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);

        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('abandoned_cart_reminders')->count());
    }

    public function test_disabled_reminders_do_not_send(): void
    {
        $this->createOrder();
        config()->set('abandoned_cart.enabled', false);

        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);
        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('abandoned_cart_reminders')->count());
    }

    public function test_dry_run_checks_candidates_without_sending_or_claiming_them(): void
    {
        $this->createOrder();

        $this->artisan('orders:send-abandoned-cart-reminders', ['--dry-run' => true])->assertExitCode(0);
        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('abandoned_cart_reminders')->count());
        $this->assertSame(0, DB::table('order_history')->count());

        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);
        Mail::assertSent(UnfinishedOrderReminder::class, 1);
    }

    public function test_command_respects_the_total_batch_limit(): void
    {
        foreach (['first@example.com', 'second@example.com', 'third@example.com'] as $email) {
            $this->createOrder(['payment_email' => $email]);
        }

        $this->artisan('orders:send-abandoned-cart-reminders', ['--limit' => 2])->assertExitCode(0);

        Mail::assertSent(UnfinishedOrderReminder::class, 2);
        $this->assertSame(2, DB::table('abandoned_cart_reminders')->count());
    }

    public function test_expired_zuzi20_is_not_advertised_in_later_reminders(): void
    {
        Carbon::setTestNow('2026-11-15 11:00:00');
        $this->createOrder(['created_at' => '2026-11-15 10:00:00']);

        $this->artisan('orders:send-abandoned-cart-reminders')->assertExitCode(0);
        Mail::assertSent(UnfinishedOrderReminder::class, function (UnfinishedOrderReminder $mail) {
            $html = $this->renderReminder($mail);

            return str_contains($html, 'Odabrana knjiga')
                && str_contains($html, 'Dovrši kupnju')
                && ! str_contains($html, 'ZUZI20');
        });
    }

    private function renderReminder(UnfinishedOrderReminder $mail): string
    {
        $mail->build();

        return view($mail->view, $mail->buildViewData())->render();
    }

    private function recordIssuedPromotion(Order $order): void
    {
        DB::table('product_actions')->insert([
            'title' => UnfinishedOrderPromoService::TITLE_PREFIX . $order->id,
            'group' => 'total', 'coupon' => 'HVALA10-TEST123', 'discount' => 10,
            'type' => 'P', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function createOrder(array $overrides = [], bool $withProduct = true): Order
    {
        $createdAt = $overrides['created_at'] ?? '2026-11-06 10:00:00';
        $id = DB::table('orders')->insertGetId(array_merge([
            'user_id' => 0,
            'affiliate_id' => 0,
            'order_status_id' => 8,
            'payment_fname' => 'Ana',
            'payment_lname' => 'Primjer',
            'payment_email' => 'ana@example.com',
            'payment_code' => 'cod',
            'shipping_method' => 'GLS',
            'shipping_state' => 'Croatia',
            'total' => 18,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'unfinished_at' => $createdAt,
        ], $overrides));

        if ($withProduct) {
            $productId = DB::table('products')->insertGetId([
                'name' => 'Odabrana knjiga', 'sku' => 'BOOK-' . $id,
                'status' => 1, 'quantity' => 5, 'price' => 18,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('order_products')->insert([
                'order_id' => $id, 'product_id' => $productId, 'name' => 'Odabrana knjiga',
                'quantity' => 1, 'org_price' => 18, 'price' => 18, 'total' => 18,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return Order::findOrFail($id);
    }

    private function history(Order $order, string $comment, int $status = 0, ?string $createdAt = null): void
    {
        DB::table('order_history')->insert([
            'order_id' => $order->id, 'user_id' => 0, 'status' => $status, 'comment' => $comment,
            'created_at' => $createdAt ?? now(), 'updated_at' => $createdAt ?? now(),
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->default(0);
            $table->unsignedBigInteger('affiliate_id')->default(0);
            $table->unsignedInteger('order_status_id')->default(8);
            foreach (['payment_fname', 'payment_lname', 'payment_email', 'payment_code', 'shipping_method', 'shipping_state'] as $column) {
                $table->string($column)->nullable();
            }
            $table->decimal('total', 15, 4)->default(0);
            $table->timestamp('checkout_processed_at')->nullable();
            $table->timestamp('unfinished_at')->nullable();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku');
            $table->boolean('status')->default(true);
            $table->integer('quantity')->default(0);
            $table->decimal('price', 15, 4)->default(0);
            $table->string('image')->nullable();
            $table->unsignedBigInteger('publisher_id')->nullable();
            $table->timestamps();
        });
        Schema::create('order_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->string('name');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('org_price', 15, 4)->default(0);
            $table->decimal('price', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
            $table->timestamps();
        });
        Schema::create('order_total', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('code')->nullable();
            $table->string('title')->nullable();
            $table->decimal('value', 15, 4)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('order_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->boolean('success')->default(false);
            $table->timestamps();
        });
        Schema::create('order_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('user_id')->default(0);
            $table->unsignedInteger('status')->default(0);
            $table->text('comment')->nullable();
            $table->timestamps();
        });
        Schema::create('product_actions', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('type')->nullable();
            $table->string('group')->nullable();
            $table->text('links')->nullable();
            $table->decimal('discount', 15, 4)->default(0);
            $table->string('coupon')->nullable();
            $table->text('data')->nullable();
            $table->boolean('status')->default(true);
            $table->unsignedInteger('quantity')->default(0);
            $table->boolean('once_per_email')->default(false);
            $table->timestamp('date_start')->nullable();
            $table->timestamp('date_end')->nullable();
            $table->timestamps();
        });
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('code');
            $table->string('key');
            $table->text('value')->nullable();
            $table->boolean('json')->default(false);
            $table->timestamps();
        });
        Schema::create('abandoned_cart_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedTinyInteger('sequence');
            $table->string('source', 16);
            $table->string('recipient_email');
            $table->char('recipient_key', 64)->index();
            $table->dateTime('scheduled_for');
            $table->dateTime('attempted_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'sequence']);
        });
    }
}
