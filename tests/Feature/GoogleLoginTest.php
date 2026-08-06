<?php

namespace Tests\Feature;

use App\Helpers\Helper;
use App\Models\Back\Settings\Settings;
use App\Models\User;
use App\Models\UserDetail;
use App\Services\GoogleLoginSettingsService;
use App\Services\GoogleOidcService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Crypt;
use Mockery;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = '1234567890-test.apps.googleusercontent.com';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'services.google_login.enabled' => false,
            'services.google_login.client_id' => '',
            'services.google_login.client_secret' => '',
        ]);

        Helper::flushCache('settings', 'authgoogle_login');
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_admin_can_save_encrypted_google_login_settings(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->patch(route('google-login.update'), [
            'enabled' => '1',
            'client_id' => self::CLIENT_ID,
            'client_secret' => 'GOCSPX-very-secret-value',
        ]);

        $response
            ->assertRedirect(route('google-login.edit'))
            ->assertSessionHas('success');

        $stored = Settings::query()
            ->where('code', 'auth')
            ->where('key', 'google_login')
            ->value('value');

        $this->assertIsString($stored);
        $this->assertStringNotContainsString('GOCSPX-very-secret-value', $stored);

        $settings = app(GoogleLoginSettingsService::class)->get();
        $this->assertTrue($settings['enabled']);
        $this->assertSame(self::CLIENT_ID, $settings['client_id']);
        $this->assertSame('GOCSPX-very-secret-value', $settings['client_secret']);

        $this->actingAs($admin)
            ->get(route('google-login.edit'))
            ->assertOk()
            ->assertSee(route('google.login.callback'))
            ->assertSee('Tajni ključ je spremljen šifrirano.')
            ->assertDontSee('GOCSPX-very-secret-value');
    }

    public function test_google_button_is_only_rendered_when_login_is_configured_and_enabled(): void
    {
        $disabledHtml = view('front.layouts.modals.login')->render();
        $this->assertStringNotContainsString('Nastavi s Google računom', $disabledHtml);

        $this->enableGoogleLogin();

        $enabledHtml = view('front.layouts.modals.login')->render();
        $this->assertStringContainsString('Nastavi s Google računom', $enabledHtml);
        $this->assertStringContainsString(route('google.login.redirect'), $enabledHtml);
    }

    public function test_google_login_redirect_creates_secure_transaction_and_pkce_request(): void
    {
        $this->enableGoogleLogin();

        $response = $this->get(route('google.login.redirect', [
            'redirect' => route('index'),
        ]));

        $response->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth');
        $response->assertSessionHas('google_login_transaction', function (array $transaction) {
            return strlen($transaction['state']) >= 40
                && strlen($transaction['nonce']) >= 40
                && strlen($transaction['code_verifier']) >= 80
                && $transaction['redirect'] === route('index');
        });

        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertSame(self::CLIENT_ID, $query['client_id']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame('openid email profile', $query['scope']);
        $this->assertNotEmpty($query['state']);
        $this->assertNotEmpty($query['nonce']);
        $this->assertNotEmpty($query['code_challenge']);
    }

    public function test_google_callback_logs_in_existing_active_customer(): void
    {
        $this->enableGoogleLogin();

        $user = User::factory()->create(['email' => 'kupac@gmail.com']);
        UserDetail::query()->create([
            'user_id' => $user->id,
            'fname' => 'Google',
            'lname' => 'Kupac',
            'role' => 'customer',
            'status' => 1,
        ]);

        $oidc = Mockery::mock(GoogleOidcService::class);
        $oidc->shouldReceive('exchangeAuthorizationCode')
            ->once()
            ->with(
                self::CLIENT_ID,
                'GOCSPX-test-secret',
                route('google.login.callback'),
                'authorization-code',
                'code-verifier'
            )
            ->andReturn(['id_token' => 'signed-id-token']);
        $oidc->shouldReceive('verifyIdToken')
            ->once()
            ->with('signed-id-token', self::CLIENT_ID, 'nonce')
            ->andReturn([
                'sub' => 'google-account-id',
                'email' => 'kupac@gmail.com',
                'email_verified' => true,
            ]);
        $this->app->instance(GoogleOidcService::class, $oidc);

        $response = $this
            ->withSession(['google_login_transaction' => [
                'state' => 'expected-state',
                'nonce' => 'nonce',
                'code_verifier' => 'code-verifier',
                'redirect' => null,
                'created_at' => time(),
            ]])
            ->get(route('google.login.callback', [
                'state' => 'expected-state',
                'code' => 'authorization-code',
            ]));

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('moj-racun'));
        $response->assertSessionMissing('google_login_transaction');
    }

    public function test_google_callback_does_not_register_unknown_account(): void
    {
        $this->enableGoogleLogin();

        $oidc = Mockery::mock(GoogleOidcService::class);
        $oidc->shouldReceive('exchangeAuthorizationCode')->once()->andReturn([
            'id_token' => 'signed-id-token',
        ]);
        $oidc->shouldReceive('verifyIdToken')->once()->andReturn([
            'sub' => 'google-account-id',
            'email' => 'novi-kupac@gmail.com',
            'email_verified' => true,
        ]);
        $this->app->instance(GoogleOidcService::class, $oidc);

        $response = $this
            ->withSession(['google_login_transaction' => [
                'state' => 'expected-state',
                'nonce' => 'nonce',
                'code_verifier' => 'code-verifier',
                'redirect' => null,
                'created_at' => time(),
            ]])
            ->get(route('google.login.callback', [
                'state' => 'expected-state',
                'code' => 'authorization-code',
            ]));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'novi-kupac@gmail.com']);
        $response
            ->assertRedirect(route('index'))
            ->assertSessionHas('auth_error', 'Za ovu Google e-mail adresu ne postoji korisnički račun. Prijavite se zaporkom ili izradite račun.');
    }

    public function test_google_callback_logs_in_an_existing_active_admin_account(): void
    {
        $this->enableGoogleLogin();

        $admin = User::factory()->create(['email' => 'admin@gmail.com']);
        UserDetail::query()->create([
            'user_id' => $admin->id,
            'fname' => 'Admin',
            'lname' => 'Korisnik',
            'role' => 'admin',
            'status' => 1,
        ]);

        $oidc = Mockery::mock(GoogleOidcService::class);
        $oidc->shouldReceive('exchangeAuthorizationCode')->once()->andReturn([
            'id_token' => 'signed-id-token',
        ]);
        $oidc->shouldReceive('verifyIdToken')->once()->andReturn([
            'sub' => 'google-admin-id',
            'email' => 'admin@gmail.com',
            'email_verified' => true,
        ]);
        $this->app->instance(GoogleOidcService::class, $oidc);

        $response = $this
            ->withSession(['google_login_transaction' => [
                'state' => 'expected-state',
                'nonce' => 'nonce',
                'code_verifier' => 'code-verifier',
                'redirect' => route('index'),
                'created_at' => time(),
            ]])
            ->get(route('google.login.callback', [
                'state' => 'expected-state',
                'code' => 'authorization-code',
            ]));

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('index'));
    }

    public function test_google_callback_sends_an_admin_with_two_factor_enabled_to_the_challenge(): void
    {
        $this->enableGoogleLogin();

        $admin = User::factory()->create([
            'email' => 'admin-2fa@gmail.com',
            'two_factor_secret' => Crypt::encryptString('two-factor-secret'),
        ]);
        UserDetail::query()->create([
            'user_id' => $admin->id,
            'fname' => 'Admin',
            'lname' => 'Zaštićen',
            'role' => 'admin',
            'status' => 1,
        ]);

        $oidc = Mockery::mock(GoogleOidcService::class);
        $oidc->shouldReceive('exchangeAuthorizationCode')->once()->andReturn([
            'id_token' => 'signed-id-token',
        ]);
        $oidc->shouldReceive('verifyIdToken')->once()->andReturn([
            'sub' => 'google-admin-2fa-id',
            'email' => 'admin-2fa@gmail.com',
            'email_verified' => true,
        ]);
        $this->app->instance(GoogleOidcService::class, $oidc);

        $response = $this
            ->withSession(['google_login_transaction' => [
                'state' => 'expected-state',
                'nonce' => 'nonce',
                'code_verifier' => 'code-verifier',
                'redirect' => null,
                'created_at' => time(),
            ]])
            ->get(route('google.login.callback', [
                'state' => 'expected-state',
                'code' => 'authorization-code',
            ]));

        $this->assertGuest();
        $response
            ->assertRedirect(route('two-factor.login'))
            ->assertSessionHas('login.id', $admin->id)
            ->assertSessionHas('login.remember', true)
            ->assertSessionHas('url.intended', route('dashboard'));
    }

    public function test_google_login_rejects_external_redirects(): void
    {
        $this->enableGoogleLogin();

        $this->get(route('google.login.redirect', [
            'redirect' => 'https://attacker.example/phishing',
        ]))->assertSessionHas('google_login_transaction', function (array $transaction) {
            return $transaction['redirect'] === null;
        });
    }

    private function enableGoogleLogin(): void
    {
        app(GoogleLoginSettingsService::class)->save([
            'enabled' => true,
            'client_id' => self::CLIENT_ID,
            'client_secret' => 'GOCSPX-test-secret',
        ]);
    }
}
