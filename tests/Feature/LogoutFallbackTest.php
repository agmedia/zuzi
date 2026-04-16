<?php

namespace Tests\Feature;

use App\Http\Responses\LogoutResponse;
use Tests\TestCase;

class LogoutFallbackTest extends TestCase
{
    public function test_logout_get_redirects_home_with_success_message(): void
    {
        $response = $this->get('/logout');

        $response->assertRedirect(route('index'));
        $response->assertSessionHas('success', LogoutResponse::MESSAGE);
    }
}
