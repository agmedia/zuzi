<?php

namespace Tests\Feature;

use App\Models\Back\Marketing\Action;
use App\Mail\UnfinishedOrderPromo;
use App\Mail\UnfinishedOrderReminder;
use App\Models\User;
use App\Services\UnfinishedOrderPromoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendUnfinishedOrderPromoEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_unfinished_order_promo_email_can_be_sent(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-04-27 10:00:00');

        $user = User::factory()->create();
        $orderId = $this->createOrder(config('settings.order.status.unfinished'), 'promo@example.com');

        $response = $this->actingAs($user)->postJson(route('api.order.send.unfinished-promo'), [
            'order_id' => $orderId,
            'discount' => 10,
        ]);

        $response->assertOk()->assertJson([
            'message' => 'Promo mail je uspješno poslan.',
        ]);

        $action = Action::query()
            ->where('title', 'Promo za nedovrsenu narudzbu #' . $orderId)
            ->where('group', 'total')
            ->first();

        $this->assertNotNull($action);
        $this->assertSame('P', $action->type);
        $this->assertSame(10.0, (float) $action->discount);
        $this->assertSame(1, (int) $action->quantity);
        $this->assertSame(1, (int) $action->status);
        $this->assertTrue(str_starts_with((string) $action->coupon, 'HVALA10-'));
        $this->assertSame(15, strlen((string) $action->coupon));
        $this->assertSame('2026-05-04 10:00:00', Carbon::make($action->date_end)->format('Y-m-d H:i:s'));

        Mail::assertSent(UnfinishedOrderPromo::class, function (UnfinishedOrderPromo $mail) use ($orderId, $action) {
            $rendered = view('emails.unfinished-order-promo', [
                'order' => $mail->order,
                'promoAction' => $mail->promoAction,
            ])->render();

            return $mail->hasTo('promo@example.com')
                && (int) $mail->order->id === $orderId
                && (int) $mail->promoAction->id === (int) $action->id
                && $mail->build()->subject === 'Tvoja nagrada čeka 🎁 (vrijedi još kratko)'
                && str_contains($rendered, 'primijetili smo da si ostavio/la nekoliko odličnih naslova u svojoj košarici')
                && str_contains($rendered, 'Šteta bi bilo da ti netko drugi uzme ono što si već odabrao/la');
        });

        $this->assertDatabaseHas('order_history', [
            'order_id' => $orderId,
            'user_id' => $user->id,
            'status' => 0,
        ]);

        $historyComment = DB::table('order_history')
            ->where('order_id', $orderId)
            ->value('comment');

        $this->assertStringContainsString('Poslan promo email za nedovrsenu narudzbu.', (string) $historyComment);
        $this->assertStringContainsString((string) $action->coupon, (string) $historyComment);
        $this->assertStringContainsString('Popust: -10%.', (string) $historyComment);
        $this->assertStringContainsString('04.05.2026 10:00', (string) $historyComment);

        Carbon::setTestNow();
    }

    public function test_unfinished_order_promo_email_can_be_sent_with_five_percent_discount(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-04-27 10:00:00');

        $user = User::factory()->create();
        $orderId = $this->createOrder(config('settings.order.status.unfinished'), 'promo@example.com');

        $response = $this->actingAs($user)->postJson(route('api.order.send.unfinished-promo'), [
            'order_id' => $orderId,
            'discount' => 5,
        ]);

        $response->assertOk()->assertJson([
            'message' => 'Promo mail je uspješno poslan.',
        ]);

        $action = Action::query()
            ->where('title', 'Promo za nedovrsenu narudzbu #' . $orderId)
            ->where('group', 'total')
            ->first();

        $this->assertNotNull($action);
        $this->assertSame(5.0, (float) $action->discount);
        $this->assertTrue(str_starts_with((string) $action->coupon, 'HVALA5-'));
        $this->assertSame(14, strlen((string) $action->coupon));

        Mail::assertSent(UnfinishedOrderPromo::class, function (UnfinishedOrderPromo $mail) use ($orderId, $action) {
            $rendered = view('emails.unfinished-order-promo', [
                'order' => $mail->order,
                'promoAction' => $mail->promoAction,
            ])->render();

            return $mail->hasTo('promo@example.com')
                && (int) $mail->order->id === $orderId
                && (int) $mail->promoAction->id === (int) $action->id
                && str_contains($rendered, 'TVOJA NAGRADA: -5% na sve artikle')
                && str_contains($rendered, (string) $action->coupon);
        });

        $historyComment = DB::table('order_history')
            ->where('order_id', $orderId)
            ->value('comment');

        $this->assertStringContainsString('Popust: -5%.', (string) $historyComment);

        Carbon::setTestNow();
    }

    public function test_unfinished_order_promo_email_can_be_sent_for_any_order_status(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $orderId = $this->createOrder(config('settings.order.status.new'), 'promo@example.com');

        $response = $this->actingAs($user)->postJson(route('api.order.send.unfinished-promo'), [
            'order_id' => $orderId,
            'discount' => 15,
        ]);

        $response->assertOk()->assertJson([
            'message' => 'Promo mail je uspješno poslan.',
        ]);

        $action = Action::query()
            ->where('title', 'Promo za nedovrsenu narudzbu #' . $orderId)
            ->first();

        $this->assertNotNull($action);
        $this->assertSame(15.0, (float) $action->discount);
        $this->assertSame(15, strlen((string) $action->coupon));
        Mail::assertSent(UnfinishedOrderPromo::class, function (UnfinishedOrderPromo $mail) {
            $rendered = view('emails.unfinished-order-promo', [
                'order' => $mail->order,
                'promoAction' => $mail->promoAction,
            ])->render();

            return $mail->build()->subject === 'Tvoja nagrada čeka 🎁 (vrijedi još kratko)'
                && str_contains($rendered, 'Hvala ti na nedavnoj kupnji na Zuzi')
                && ! str_contains($rendered, 'primijetili smo da si ostavio/la nekoliko odličnih naslova u svojoj košarici');
        });
    }

    public function test_unfinished_order_promo_email_cannot_be_sent_twice_for_same_order(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $orderId = $this->createOrder(config('settings.order.status.unfinished'), 'promo@example.com');

        Carbon::setTestNow('2026-04-27 10:00:00');
        $this->actingAs($user)->postJson(route('api.order.send.unfinished-promo'), [
            'order_id' => $orderId,
            'discount' => 10,
        ])->assertOk();

        Carbon::setTestNow('2026-04-29 15:30:00');
        $response = $this->actingAs($user)->postJson(route('api.order.send.unfinished-promo'), [
            'order_id' => $orderId,
            'discount' => 20,
        ]);

        $response->assertStatus(422)->assertJson([
            'error' => 'Promo mail je već poslan za ovu narudžbu.',
        ]);

        $actions = Action::query()
            ->where('title', 'Promo za nedovrsenu narudzbu #' . $orderId)
            ->get();

        $this->assertCount(1, $actions);
        $this->assertSame(10.0, (float) $actions->first()->discount);
        Mail::assertSent(UnfinishedOrderPromo::class, 1);

        Carbon::setTestNow();
    }

    public function test_unfinished_order_promo_email_cannot_be_sent_when_order_already_uses_coupon(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $orderId = $this->createOrder(config('settings.order.status.unfinished'), 'promo@example.com');
        $this->addOrderTotal($orderId, 'special', 'Kupon HVALA10-POSTOJI', -2.50);

        $response = $this->actingAs($user)->postJson(route('api.order.send.unfinished-promo'), [
            'order_id' => $orderId,
            'discount' => 10,
        ]);

        $response->assertStatus(422)->assertJson([
            'error' => 'Kupac na ovoj narudžbi već koristi kod.',
        ]);

        $this->assertNull(Action::query()
            ->where('title', 'Promo za nedovrsenu narudzbu #' . $orderId)
            ->first());
        Mail::assertNotSent(UnfinishedOrderPromo::class);
    }

    public function test_orders_index_hides_unfinished_promo_button_when_order_already_uses_coupon(): void
    {
        $user = User::factory()->create();
        $blockedOrderId = $this->createOrder(config('settings.order.status.unfinished'), 'coupon@example.com');
        $availableOrderId = $this->createOrder(config('settings.order.status.unfinished'), 'available@example.com');
        $this->addOrderTotal($blockedOrderId, 'special', 'Kupon HVALA10-POSTOJI', -2.50);

        $response = $this->actingAs($user)->get(route('orders'));

        $response->assertOk();
        $response->assertDontSee('data-unfinished-promo-btn="' . $blockedOrderId . '"', false);
        $response->assertSee('data-unfinished-promo-btn="' . $availableOrderId . '"', false);
    }

    public function test_unfinished_order_reminder_email_can_be_sent_without_promo_code(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $orderId = $this->createOrder(config('settings.order.status.unfinished'), 'reminder@example.com');

        $response = $this->actingAs($user)->postJson(route('api.order.send.unfinished-reminder'), [
            'order_id' => $orderId,
        ]);

        $response->assertOk()->assertJson([
            'message' => 'Podsjetnik je uspješno poslan.',
        ]);

        $this->assertNull(Action::query()
            ->where('title', 'Promo za nedovrsenu narudzbu #' . $orderId)
            ->first());

        Mail::assertSent(UnfinishedOrderReminder::class, function (UnfinishedOrderReminder $mail) use ($orderId) {
            $rendered = view('emails.unfinished-order-reminder', [
                'order' => $mail->order,
            ])->render();

            return $mail->hasTo('reminder@example.com')
                && (int) $mail->order->id === $orderId
                && $mail->build()->subject === 'Podsjetnik na nedovršenu narudžbu - Zuzi Shop'
                && str_contains($rendered, 'primijetili smo da tvoja narudžba nije dovršena')
                && ! str_contains($rendered, 'HVALA')
                && ! str_contains($rendered, 'Kod:')
                && ! str_contains($rendered, 'TVOJA NAGRADA');
        });

        $historyComment = DB::table('order_history')
            ->where('order_id', $orderId)
            ->value('comment');

        $this->assertSame(UnfinishedOrderPromoService::REMINDER_HISTORY_COMMENT, (string) $historyComment);
    }

    public function test_unfinished_order_reminder_email_cannot_be_sent_twice_for_same_order(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $orderId = $this->createOrder(config('settings.order.status.unfinished'), 'reminder@example.com');

        $this->actingAs($user)->postJson(route('api.order.send.unfinished-reminder'), [
            'order_id' => $orderId,
        ])->assertOk();

        $response = $this->actingAs($user)->postJson(route('api.order.send.unfinished-reminder'), [
            'order_id' => $orderId,
        ]);

        $response->assertStatus(422)->assertJson([
            'error' => 'Podsjetnik je već poslan za ovu narudžbu.',
        ]);

        $historyCount = DB::table('order_history')
            ->where('order_id', $orderId)
            ->where('comment', UnfinishedOrderPromoService::REMINDER_HISTORY_COMMENT)
            ->count();

        $this->assertSame(1, $historyCount);
        Mail::assertSent(UnfinishedOrderReminder::class, 1);
    }

    public function test_unfinished_order_reminder_email_cannot_be_sent_for_other_order_statuses(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $orderId = $this->createOrder(config('settings.order.status.new'), 'reminder@example.com');

        $response = $this->actingAs($user)->postJson(route('api.order.send.unfinished-reminder'), [
            'order_id' => $orderId,
        ]);

        $response->assertStatus(422)->assertJson([
            'error' => 'Podsjetnik se može poslati samo za nedovršenu narudžbu.',
        ]);

        Mail::assertNotSent(UnfinishedOrderReminder::class);
    }

    public function test_orders_index_shows_unfinished_reminder_when_promo_is_blocked_by_coupon(): void
    {
        $user = User::factory()->create();
        $orderId = $this->createOrder(config('settings.order.status.unfinished'), 'coupon@example.com');
        $this->addOrderTotal($orderId, 'special', 'Kupon HVALA10-POSTOJI', -2.50);

        $response = $this->actingAs($user)->get(route('orders'));

        $response->assertOk();
        $response->assertDontSee('data-unfinished-promo-btn="' . $orderId . '"', false);
        $response->assertSee('data-unfinished-reminder-btn="' . $orderId . '"', false);
        $response->assertSee('Pošalji podsjetnik', false);
    }

    public function test_orders_index_marks_unfinished_reminder_as_sent(): void
    {
        $user = User::factory()->create();
        $orderId = $this->createOrder(config('settings.order.status.unfinished'), 'reminder@example.com');
        $this->addOrderHistory($orderId, $user->id, UnfinishedOrderPromoService::REMINDER_HISTORY_COMMENT);

        $response = $this->actingAs($user)->get(route('orders'));

        $response->assertOk();
        $response->assertSee('Podsjetnik poslan', false);
        $response->assertDontSee('data-unfinished-reminder-btn="' . $orderId . '"', false);
        $response->assertSee('data-unfinished-promo-btn="' . $orderId . '"', false);
    }

    private function createOrder(int $statusId, string $email): int
    {
        return (int) DB::table('orders')->insertGetId([
            'user_id' => 0,
            'affiliate_id' => 0,
            'order_status_id' => $statusId,
            'invoice' => null,
            'total' => 25,
            'payment_fname' => 'Ana',
            'payment_lname' => 'Anić',
            'payment_address' => 'Test ulica 1',
            'payment_zip' => '10000',
            'payment_city' => 'Zagreb',
            'payment_phone' => null,
            'payment_email' => $email,
            'payment_method' => 'Kartice',
            'payment_code' => 'corvus',
            'payment_card' => null,
            'payment_installment' => 0,
            'shipping_fname' => 'Ana',
            'shipping_lname' => 'Anić',
            'shipping_address' => 'Test ulica 1',
            'shipping_zip' => '10000',
            'shipping_city' => 'Zagreb',
            'shipping_phone' => null,
            'shipping_email' => $email,
            'shipping_method' => 'Dostava',
            'shipping_code' => 'gls',
            'company' => '',
            'oib' => '',
            'comment' => null,
            'tracking_code' => '',
            'shipped' => false,
            'printed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function addOrderTotal(int $orderId, string $code, string $title, float $value): void
    {
        DB::table('order_total')->insert([
            'order_id' => $orderId,
            'code' => $code,
            'title' => $title,
            'value' => $value,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function addOrderHistory(int $orderId, int $userId, string $comment): void
    {
        DB::table('order_history')->insert([
            'order_id' => $orderId,
            'user_id' => $userId,
            'status' => 0,
            'comment' => $comment,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
