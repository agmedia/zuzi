<?php

namespace Tests\Feature;

use App\Mail\OrderReviewRequest;
use App\Models\Back\Marketing\Action;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendReviewRequestEmailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_request_email_omits_coupon_while_review_promos_are_paused(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-06-10 09:15:00');

        $orderId = $this->createCompletedReviewableOrder('review@example.com', '2026-05-16 09:15:00');

        $this->artisan('send:review-requests')->assertExitCode(0);

        Mail::assertSent(OrderReviewRequest::class, function (OrderReviewRequest $mail) use ($orderId) {
            $rendered = $this->renderReviewRequestMail($mail);

            return $mail->hasTo('review@example.com')
                && (int) $mail->order->id === $orderId
                && $mail->promoAction === null
                && ! str_contains($rendered, 'Vaš kod za sljedeću kupnju')
                && ! str_contains($rendered, 'ostvaruje 20% popusta')
                && str_contains($rendered, 'Podijeli dojam');
        });

        $this->assertDatabaseMissing('product_actions', [
            'title' => 'Promo za dojam narudzbe #' . $orderId,
        ]);

        $this->assertNotNull(DB::table('orders')->where('id', $orderId)->value('review_request_sent_at'));
    }

    public function test_review_request_coupon_sending_resumes_after_pause_window(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-07-01 09:15:00');

        $orderId = $this->createCompletedReviewableOrder('review@example.com', '2026-06-06 09:15:00');

        $this->artisan('send:review-requests')->assertExitCode(0);

        $action = Action::query()
            ->where('title', 'Promo za dojam narudzbe #' . $orderId)
            ->first();

        $this->assertNotNull($action);
        $this->assertTrue(str_starts_with((string) $action->coupon, 'DOJAM20-'));

        Mail::assertSent(OrderReviewRequest::class, function (OrderReviewRequest $mail) use ($orderId, $action) {
            $rendered = $this->renderReviewRequestMail($mail);

            return $mail->hasTo('review@example.com')
                && (int) $mail->order->id === $orderId
                && (int) optional($mail->promoAction)->id === (int) $action->id
                && str_contains($rendered, 'Vaš kod za sljedeću kupnju')
                && str_contains($rendered, (string) $action->coupon);
        });
    }

    public function test_review_request_test_email_also_respects_coupon_pause(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-06-10 09:15:00');

        $orderId = $this->createCompletedReviewableOrder('customer@example.com', '2026-05-16 09:15:00');

        $this->artisan('send:review-request-test', [
            'email' => 'test@example.com',
            'orderId' => $orderId,
        ])->assertExitCode(0);

        Mail::assertSent(OrderReviewRequest::class, function (OrderReviewRequest $mail) use ($orderId) {
            $rendered = $this->renderReviewRequestMail($mail);

            return $mail->hasTo('test@example.com')
                && (int) $mail->order->id === $orderId
                && $mail->promoAction === null
                && ! str_contains($rendered, 'Vaš kod za sljedeću kupnju');
        });

        $this->assertDatabaseMissing('product_actions', [
            'title' => 'Promo za dojam narudzbe #' . $orderId,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function createCompletedReviewableOrder(string $email, string $completedAt): int
    {
        $completedAt = Carbon::parse($completedAt);
        $completedStatusId = (int) config('settings.order.status.send');
        $productId = $this->createProduct();

        $orderId = (int) DB::table('orders')->insertGetId([
            'user_id' => 0,
            'affiliate_id' => 0,
            'order_status_id' => $completedStatusId,
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
            'created_at' => $completedAt,
            'updated_at' => $completedAt,
        ]);

        DB::table('order_products')->insert([
            'order_id' => $orderId,
            'product_id' => $productId,
            'name' => 'Test knjiga',
            'quantity' => 1,
            'org_price' => 25,
            'discount' => null,
            'price' => 25,
            'total' => 25,
            'created_at' => $completedAt,
            'updated_at' => $completedAt,
        ]);

        DB::table('order_history')->insert([
            'order_id' => $orderId,
            'user_id' => 0,
            'status' => $completedStatusId,
            'comment' => 'Narudžba završena.',
            'created_at' => $completedAt,
            'updated_at' => $completedAt,
        ]);

        return $orderId;
    }

    private function createProduct(): int
    {
        return (int) DB::table('products')->insertGetId([
            'author_id' => 0,
            'publisher_id' => 0,
            'action_id' => 0,
            'name' => 'Test knjiga',
            'sku' => 'REVTEST1',
            'ean' => null,
            'description' => null,
            'slug' => 'test-knjiga',
            'url' => '/knjiga/test-knjiga',
            'image' => null,
            'price' => 25,
            'quantity' => 1,
            'tax_id' => 0,
            'special' => null,
            'special_from' => null,
            'special_to' => null,
            'meta_title' => null,
            'meta_description' => null,
            'related_products' => null,
            'pages' => null,
            'dimensions' => null,
            'origin' => null,
            'letter' => null,
            'condition' => null,
            'binding' => null,
            'year' => null,
            'viewed' => 0,
            'sort_order' => 0,
            'push' => false,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function renderReviewRequestMail(OrderReviewRequest $mail): string
    {
        return view('emails.order-review-request', [
            'order' => $mail->order,
            'promoAction' => $mail->promoAction,
            'reviewItems' => $mail->reviewItems,
        ])->render();
    }
}
