<?php

namespace Tests\Feature;

use App\Helpers\Session\CheckoutSession;
use App\Http\Livewire\Front\Checkout;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutAddressAutofillTest extends TestCase
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
        config()->set('session.cart', 'zuzi_cart');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    public function test_logged_in_checkout_fills_empty_session_address_from_profile(): void
    {
        $user = $this->createUserWithDetails([
            'email' => 'tomislav@example.test',
            'fname' => 'Tomislav',
            'lname' => 'Juresa',
            'phone' => '0911234567',
            'address' => 'Ilica 1',
            'city' => 'Zagreb',
            'zip' => '10000',
            'company' => 'Zuzi d.o.o.',
            'oib' => '12345678901',
            'state' => 'Slovenia',
        ]);

        $this->actingAs($user);
        session()->start();

        CheckoutSession::setAddress([
            'fname' => '',
            'lname' => ' ',
            'email' => null,
            'phone' => '',
            'address' => '',
            'city' => '',
            'company' => '',
            'oib' => '',
            'zip' => '',
            'state' => 'Croatia',
        ]);

        $expected = [
            'fname' => 'Tomislav',
            'lname' => 'Juresa',
            'email' => 'tomislav@example.test',
            'phone' => '0911234567',
            'address' => 'Ilica 1',
            'city' => 'Zagreb',
            'company' => 'Zuzi d.o.o.',
            'oib' => '12345678901',
            'zip' => '10000',
            'state' => 'Slovenia',
        ];

        Livewire::test(Checkout::class, ['step' => 'podaci'])
            ->assertSet('address', $expected);

        $this->assertSame($expected, CheckoutSession::getAddress());
    }

    public function test_logged_in_checkout_preserves_entered_session_fields_and_fills_blanks(): void
    {
        $user = $this->createUserWithDetails([
            'email' => 'profile@example.test',
            'fname' => 'Profile',
            'lname' => 'User',
            'phone' => '0997654321',
            'address' => 'Profilna 2',
            'city' => 'Split',
            'zip' => '21000',
            'company' => 'Profile Company',
            'oib' => '10987654321',
            'state' => 'Slovenia',
        ]);

        $this->actingAs($user);
        session()->start();

        CheckoutSession::setAddress([
            'fname' => 'Rucno',
            'lname' => '',
            'email' => 'manual@example.test',
            'phone' => '',
            'address' => '',
            'city' => 'Osijek',
            'company' => '',
            'oib' => '',
            'zip' => '',
            'state' => 'Croatia',
        ]);

        $expected = [
            'fname' => 'Rucno',
            'lname' => 'User',
            'email' => 'manual@example.test',
            'phone' => '0997654321',
            'address' => 'Profilna 2',
            'city' => 'Osijek',
            'company' => 'Profile Company',
            'oib' => '10987654321',
            'zip' => '21000',
            'state' => 'Croatia',
        ];

        Livewire::test(Checkout::class, ['step' => 'podaci'])
            ->assertSet('address', $expected);

        $this->assertSame($expected, CheckoutSession::getAddress());
    }

    public function test_guest_checkout_keeps_blank_address_with_default_country(): void
    {
        session()->start();

        CheckoutSession::setAddress([
            'fname' => '',
            'lname' => '',
            'email' => '',
            'phone' => '',
            'address' => '',
            'city' => '',
            'company' => '',
            'oib' => '',
            'zip' => '',
            'state' => '',
        ]);

        $expected = [
            'fname' => '',
            'lname' => '',
            'email' => '',
            'phone' => '',
            'address' => '',
            'city' => '',
            'company' => '',
            'oib' => '',
            'zip' => '',
            'state' => 'Croatia',
        ];

        Livewire::test(Checkout::class, ['step' => 'podaci'])
            ->assertSet('address', $expected);
    }

    private function createUserWithDetails(array $details): User
    {
        $user = User::factory()->create([
            'email' => $details['email'],
        ]);

        $user->details()->create([
            'fname' => $details['fname'],
            'lname' => $details['lname'],
            'address' => $details['address'],
            'zip' => $details['zip'],
            'city' => $details['city'],
            'state' => $details['state'],
            'phone' => $details['phone'],
            'company' => $details['company'],
            'oib' => $details['oib'],
            'avatar' => 'media/avatars/avatar1.jpg',
            'bio' => '',
            'social' => '',
            'role' => 'customer',
            'status' => 1,
        ]);

        return $user->fresh('details');
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('user_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('fname');
            $table->string('lname')->nullable();
            $table->string('address')->nullable();
            $table->string('zip')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('oib')->nullable();
            $table->string('avatar')->nullable();
            $table->longText('bio')->nullable();
            $table->string('social')->nullable();
            $table->string('role');
            $table->boolean('status')->default(1);
            $table->timestamps();
        });
    }
}
