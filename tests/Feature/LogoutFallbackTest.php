<?php

namespace Tests\Feature;

use App\Http\Responses\LogoutResponse;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

class LogoutFallbackTest extends TestCase
{
    public function test_logout_get_redirects_home_with_success_message(): void
    {
        $response = $this->get('/logout');

        $response->assertRedirect(route('index'));
        $response->assertSessionHas('success', LogoutResponse::MESSAGE);
    }

    public function test_logout_token_mismatch_redirects_home_with_success_message(): void
    {
        $session = $this->app['session.store'];
        $session->start();

        $request = Request::create('/logout', 'POST');
        $request->setLaravelSession($session);

        $response = $this->app->make(ExceptionHandler::class)->render(
            $request,
            new TokenMismatchException('CSRF token mismatch.')
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('index'), $response->headers->get('Location'));
        $this->assertSame(LogoutResponse::MESSAGE, $session->get('success'));
    }
}
