<?php

namespace Tests\Feature;

use App\Mail\AccountNoticeMail;
use App\Models\User;
use App\Models\UserDetail;
use App\Services\AccountNoticeMailService;
use App\Services\AccountNoticeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountNoticeMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_notice_recipients_returns_limited_remaining_customers(): void
    {
        $admin = $this->createUserWithRole('admin@example.com', 'admin');
        $firstCustomer = $this->createUserWithRole('first@example.com', 'customer');
        $this->createUserWithRole('second@example.com', 'customer');
        $this->createUserWithRole('inactive@example.com', 'customer', 0);
        $this->createUserWithRole('editor@example.com', 'editor');

        $this->saveNotice();

        $response = $this->actingAs($admin)->postJson(route('account.notice.recipients'), [
            'limit' => 1,
            'delay' => 8,
        ]);

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('batch_limit', 1)
            ->assertJsonPath('delay_seconds', 8)
            ->assertJsonPath('stats.total', 3)
            ->assertJsonPath('stats.sent', 0)
            ->assertJsonPath('stats.remaining', 3)
            ->assertJsonPath('user_ids.0', $firstCustomer->id);
    }

    public function test_account_notice_mail_is_logged_and_excluded_from_next_batch(): void
    {
        Mail::fake();

        $admin = $this->createUserWithRole('admin@example.com', 'admin');
        $firstCustomer = $this->createUserWithRole('first@example.com', 'customer');
        $secondCustomer = $this->createUserWithRole('second@example.com', 'customer');

        $notice = $this->saveNotice();

        $response = $this->actingAs($admin)->postJson(route('account.notice.mail.send'), [
            'user_id' => $firstCustomer->id,
        ]);

        $response->assertOk()->assertJson([
            'message' => 'Obavijest je uspješno poslana.',
            'email' => 'first@example.com',
        ]);

        Mail::assertSent(AccountNoticeMail::class, function (AccountNoticeMail $mail) use ($firstCustomer) {
            $rendered = view('emails.account-notice', [
                'notice' => $mail->notice,
                'validUntil' => $mail->validUntil,
                'user' => $mail->user,
            ])->render();

            return $mail->hasTo('first@example.com')
                && (int) $mail->user->id === (int) $firstCustomer->id
                && $mail->build()->subject === 'Tvoj glas nam puno znači'
                && str_contains($rendered, 'HVALAODSRCA')
                && str_contains($rendered, 'GLASAJ ZA ZUZI SHOP');
        });

        $this->assertDatabaseHas('account_notice_mail_logs', [
            'user_id' => $firstCustomer->id,
            'email' => 'first@example.com',
            'notice_hash' => app(AccountNoticeMailService::class)->noticeHash($notice),
        ]);

        $nextBatch = $this->actingAs($admin)->postJson(route('account.notice.recipients'), [
            'limit' => 10,
            'delay' => 8,
        ]);

        $nextBatch->assertOk()
            ->assertJsonPath('stats.total', 2)
            ->assertJsonPath('stats.sent', 1)
            ->assertJsonPath('stats.remaining', 1)
            ->assertJsonPath('user_ids.0', $secondCustomer->id);

        $duplicate = $this->actingAs($admin)->postJson(route('account.notice.mail.send'), [
            'user_id' => $firstCustomer->id,
        ]);

        $duplicate->assertStatus(422)->assertJson([
            'error' => 'Obavijest je već poslana ovom korisniku.',
        ]);

        Mail::assertSent(AccountNoticeMail::class, 1);
    }

    public function test_account_notice_test_mail_goes_to_configured_test_address_without_logging(): void
    {
        Mail::fake();

        $admin = $this->createUserWithRole('admin@example.com', 'admin');
        $this->saveNotice();

        $response = $this->actingAs($admin)->postJson(route('account.notice.mail.test'));

        $response->assertOk()->assertJson([
            'message' => 'Testni mail je uspješno poslan.',
            'email' => AccountNoticeMailService::TEST_EMAIL,
        ]);

        Mail::assertSent(AccountNoticeMail::class, function (AccountNoticeMail $mail) {
            return $mail->hasTo(AccountNoticeMailService::TEST_EMAIL)
                && $mail->user === null
                && $mail->build()->subject === 'Tvoj glas nam puno znači';
        });

        $this->assertDatabaseCount('account_notice_mail_logs', 0);
    }

    private function saveNotice(): array
    {
        $notice = [
            'active' => true,
            'title' => 'Tvoj glas nam puno znači',
            'intro' => 'Zuzi Shop ponovno je u izboru za najbolju knjižaru u Hrvatskoj.',
            'coupon_label' => 'Kao malo hvala od srca, darujemo ti',
            'coupon_code' => 'HVALAODSRCA',
            'discount_text' => '20% POPUSTA',
            'outro' => 'Glasanje traje do 1. prosinca 2026.',
            'button_text' => 'GLASAJ ZA ZUZI SHOP',
            'button_url' => 'https://min-kulture.gov.hr/glasaj/',
            'valid_until' => '2026-07-06',
        ];

        app(AccountNoticeService::class)->save($notice);

        return app(AccountNoticeService::class)->get();
    }

    private function createUserWithRole(string $email, string $role, int $status = 1): User
    {
        $user = User::factory()->create([
            'email' => $email,
        ]);

        UserDetail::query()->create([
            'user_id' => $user->id,
            'fname' => '',
            'lname' => '',
            'address' => '',
            'zip' => '',
            'city' => '',
            'state' => '',
            'phone' => '',
            'avatar' => 'media/avatars/avatar1.jpg',
            'bio' => '',
            'social' => '',
            'role' => $role,
            'status' => $status,
        ]);

        return $user;
    }
}
