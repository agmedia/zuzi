<?php

namespace Tests\Feature;

use App\Mail\ContractWithdrawalAdminMail;
use App\Mail\ContractWithdrawalReceiptMail;
use App\Helpers\Helper;
use App\Models\ContractWithdrawal;
use App\Models\User;
use App\Services\ContractWithdrawalSettingsService;
use Bouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContractWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'services.recaptcha.sitekey' => '',
            'services.recaptcha.secret' => '',
            'mail.admin' => 'info@zuzi.hr',
        ]);

        Helper::flushCache('settings', 'storecontract_withdrawal');
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_public_form_has_clear_withdrawal_action_and_legal_information(): void
    {
        $response = $this->get(route('contract-withdrawal.create'));

        $response->assertOk()
            ->assertSee('Obrazac za jednostrani raskid ugovora')
            ->assertSee('Raskid ugovora')
            ->assertSee('14 dana')
            ->assertSee('bez navođenja razloga')
            ->assertSee('Izravne troškove povrata robe snosite sami.')
            ->assertSee('Antuna Šoljana 33, 10000 Zagreb')
            ->assertSee('Potvrda sa sadržajem');
    }

    public function test_withdrawal_is_reviewed_stored_with_evidence_and_emailed_to_both_parties(): void
    {
        Mail::fake();

        app(ContractWithdrawalSettingsService::class)->save([
            'admin_email' => 'raskidi@example.test',
            'return_address' => 'ZUZI TEST, Povratna 1, Zagreb',
            'return_cost_policy' => 'consumer',
            'instructions' => 'U paket stavite referencu.',
        ]);

        $review = $this->post(route('contract-withdrawal.review'), $this->validPayload());

        $review->assertOk()
            ->assertSee('Pregledajte izjavu o raskidu')
            ->assertSee('Potvrditi raskid ugovora')
            ->assertSee('Ovime nedvosmisleno izjavljujem');

        $this->assertDatabaseCount('contract_withdrawals', 0);

        preg_match('/name="draft_token" value="([^"]+)"/', $review->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        $store = $this->post(route('contract-withdrawal.store'), [
            'draft_token' => $matches[1],
        ]);

        $store->assertRedirect(route('contract-withdrawal.create'))
            ->assertSessionHas('success')
            ->assertSessionHas('withdrawal_reference');

        $withdrawal = ContractWithdrawal::query()->firstOrFail();

        $this->assertSame('ZUZI-1001', $withdrawal->order_number);
        $this->assertSame('kupac@example.test', $withdrawal->email);
        $this->assertSame(ContractWithdrawal::STATUS_RECEIVED, $withdrawal->status);
        $this->assertNotNull($withdrawal->submitted_at);
        $this->assertNotNull($withdrawal->consumer_notified_at);
        $this->assertNotNull($withdrawal->admin_notified_at);
        $this->assertNull($withdrawal->notification_error);
        $this->assertSame(
            ContractWithdrawal::snapshotHash($withdrawal->request_snapshot),
            $withdrawal->snapshot_hash
        );

        Mail::assertSent(ContractWithdrawalReceiptMail::class, function ($mail) use ($withdrawal) {
            return $mail->hasTo('kupac@example.test')
                && $mail->withdrawal->is($withdrawal)
                && str_contains($mail->build()->subject, $withdrawal->reference);
        });

        Mail::assertSent(ContractWithdrawalAdminMail::class, function ($mail) use ($withdrawal) {
            return $mail->hasTo('raskidi@example.test')
                && $mail->withdrawal->is($withdrawal)
                && str_contains($mail->build()->subject, 'ZUZI-1001');
        });
    }

    public function test_invalid_submission_does_not_create_withdrawal(): void
    {
        $response = $this->from(route('contract-withdrawal.create'))
            ->post(route('contract-withdrawal.review'), [
                'full_name' => '',
                'email' => 'nije-email',
            ]);

        $response->assertRedirect(route('contract-withdrawal.create'))
            ->assertSessionHasErrors(['full_name', 'email', 'address_line', 'order_number', 'items']);

        $this->get(route('contract-withdrawal.create'))
            ->assertOk()
            ->assertSee('novalidate', false)
            ->assertSee('Polje ime i prezime je obavezno.')
            ->assertSee('Polje e-mail mora biti ispravna e-mail adresa.');

        $this->assertDatabaseCount('contract_withdrawals', 0);
    }

    public function test_admin_can_save_settings_process_request_and_resend_notifications(): void
    {
        Mail::fake();

        $admin = User::factory()->create();
        Bouncer::allow($admin)->everything();

        $settingsResponse = $this->actingAs($admin)
            ->patch(route('contract-withdrawal-settings.update'), [
                'admin_email' => 'admin-raskidi@example.test',
                'return_address' => 'ZUZI, Nova adresa 2, Zagreb',
                'return_cost_policy' => 'merchant',
                'instructions' => 'Robu pošaljite preporučeno.',
            ]);

        $settingsResponse
            ->assertRedirect(route('contract-withdrawal-settings.edit'))
            ->assertSessionHas('success');

        $settings = app(ContractWithdrawalSettingsService::class)->get();
        $this->assertSame('admin-raskidi@example.test', $settings['admin_email']);
        $this->assertSame('merchant', $settings['return_cost_policy']);

        $withdrawal = $this->makeWithdrawal();

        $this->actingAs($admin)
            ->get(route('contract-withdrawals.index'))
            ->assertOk()
            ->assertSee($withdrawal->reference)
            ->assertSee($withdrawal->full_name);

        $this->actingAs($admin)
            ->get(route('contract-withdrawals.show', $withdrawal))
            ->assertOk()
            ->assertSee($withdrawal->declaration)
            ->assertSee($withdrawal->items);

        $this->actingAs($admin)
            ->patch(route('contract-withdrawals.update', $withdrawal), [
                'status' => ContractWithdrawal::STATUS_COMPLETED,
                'internal_note' => 'Povrat je obrađen.',
            ])
            ->assertRedirect(route('contract-withdrawals.show', $withdrawal));

        $withdrawal->refresh();
        $this->assertSame(ContractWithdrawal::STATUS_COMPLETED, $withdrawal->status);
        $this->assertSame('Povrat je obrađen.', $withdrawal->internal_note);
        $this->assertSame($admin->id, $withdrawal->handled_by);
        $this->assertNotNull($withdrawal->completed_at);

        $this->actingAs($admin)
            ->post(route('contract-withdrawals.resend', $withdrawal))
            ->assertRedirect(route('contract-withdrawals.show', $withdrawal))
            ->assertSessionHas('success');

        Mail::assertSent(ContractWithdrawalReceiptMail::class, function ($mail) {
            return $mail->hasTo('kupac@example.test');
        });
        Mail::assertSent(ContractWithdrawalAdminMail::class, function ($mail) {
            return $mail->hasTo('admin-raskidi@example.test');
        });
    }

    private function validPayload(): array
    {
        return [
            'full_name' => 'Ana Čitatelj',
            'email' => 'kupac@example.test',
            'phone' => '+385 91 123 4567',
            'address_line' => 'Knjiška 12',
            'postal_code' => '10000',
            'city' => 'Zagreb',
            'country_code' => 'HR',
            'order_number' => 'ZUZI-1001',
            'contract_date' => '2026-07-10',
            'received_date' => '2026-07-12',
            'items' => "Knjiga A, 1 kom\nKnjiga B, 1 kom",
            'note' => '',
            'recaptcha' => '',
        ];
    }

    private function makeWithdrawal(): ContractWithdrawal
    {
        $snapshot = [
            'version' => '2026-07-29',
            'submitted_at' => now()->toIso8601String(),
            'confirmation_channel' => 'email',
            'data' => $this->validPayload(),
            'declaration' => 'Ovime raskidam ugovor ZUZI-1001.',
        ];
        return ContractWithdrawal::query()->create([
            'reference' => 'JR-20260729-ABC123',
            'submission_key' => hash('sha256', 'test-submission'),
            'order_number' => 'ZUZI-1001',
            'full_name' => 'Ana Čitatelj',
            'email' => 'kupac@example.test',
            'phone' => '+385 91 123 4567',
            'address_line' => 'Knjiška 12',
            'postal_code' => '10000',
            'city' => 'Zagreb',
            'country_code' => 'HR',
            'contract_date' => '2026-07-10',
            'received_date' => '2026-07-12',
            'items' => 'Knjiga A, 1 kom',
            'declaration' => 'Ovime raskidam ugovor ZUZI-1001.',
            'request_snapshot' => $snapshot,
            'snapshot_hash' => ContractWithdrawal::snapshotHash($snapshot),
            'status' => ContractWithdrawal::STATUS_RECEIVED,
            'locale' => 'hr',
            'submitted_at' => now(),
        ]);
    }
}
