<?php

namespace Tests\Feature;

use App\Models\Back\Marketing\Action;
use App\Mail\UnfinishedOrderPromo;
use App\Models\User;
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
                && str_contains($rendered, 'hvala ti na kupnji na Zuzi')
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
}
