<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserDetail;
use Bouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class UserImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_impersonate_user_and_return_to_admin(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
        ]);
        $customer = User::factory()->create([
            'email' => 'customer@example.com',
        ]);
        UserDetail::create([
            'user_id' => $customer->id,
            'fname' => 'Test',
            'lname' => 'Customer',
            'role' => 'customer',
        ]);

        Bouncer::allow($admin)->everything();

        $auth_session_key = Auth::guard('web')->getName();

        $response = $this->actingAs($admin)->post(route('users.impersonate', $customer));

        $response->assertRedirect(route('moj-racun'));
        $response->assertSessionHas('impersonator_id', $admin->id);
        $response->assertSessionHas($auth_session_key, $customer->id);
        $response->assertSessionHas('password_hash_web', $customer->getAuthPassword());

        Auth::forgetGuards();
        $this->get(route('moj-racun'))
            ->assertOk()
            ->assertSee('customer@example.com');

        Auth::forgetGuards();
        $response = $this->post(route('users.impersonate.stop'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionMissing('impersonator_id');
        $response->assertSessionHas($auth_session_key, $admin->id);
        $response->assertSessionHas('password_hash_web', $admin->getAuthPassword());
    }

    public function test_user_without_superadmin_permission_cannot_impersonate(): void
    {
        $admin = User::factory()->create();
        $customer = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('users.impersonate', $customer))
            ->assertForbidden();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_orders_index_links_registered_customer_to_front_impersonation(): void
    {
        $admin = User::factory()->create();
        $customer = User::factory()->create([
            'email' => 'registered@example.com',
        ]);

        Bouncer::allow($admin)->everything();

        DB::table('orders')->insert([
            'user_id' => $customer->id,
            'affiliate_id' => 0,
            'order_status_id' => (int) config('settings.order.status.unfinished'),
            'invoice' => null,
            'total' => 25,
            'payment_fname' => 'Tomislav',
            'payment_lname' => 'Juresa',
            'payment_address' => 'Test ulica 1',
            'payment_zip' => '10000',
            'payment_city' => 'Zagreb',
            'payment_phone' => null,
            'payment_email' => 'registered@example.com',
            'payment_method' => 'Kartice',
            'payment_code' => 'corvus',
            'payment_card' => null,
            'payment_installment' => 0,
            'shipping_fname' => 'Tomislav',
            'shipping_lname' => 'Juresa',
            'shipping_address' => 'Test ulica 1',
            'shipping_zip' => '10000',
            'shipping_city' => 'Zagreb',
            'shipping_phone' => null,
            'shipping_email' => 'registered@example.com',
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

        $this->actingAs($admin)
            ->get(route('orders'))
            ->assertOk()
            ->assertSee(route('users.impersonate', ['user' => $customer]), false)
            ->assertSee('Otvori front profil kupca', false)
            ->assertSee('Tomislav Juresa', false);
    }
}
