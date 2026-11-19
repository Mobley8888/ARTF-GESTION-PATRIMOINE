<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_public_account_is_created_with_consultation_permissions_and_can_log_in(): void
    {
        $password = Str::random(20).'Aa1!';

        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('register'));

        $this->post(route('register.store'), [
            'first_name' => 'Awa',
            'last_name' => 'Diallo',
            'email' => 'AWA.DIALLO@example.test',
            'password' => $password,
            'password_confirmation' => $password,
            'role' => UserRole::SuperAdmin->value,
            'is_active' => false,
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $user = User::query()->where('email', 'awa.diallo@example.test')->firstOrFail();

        $this->assertSame('Awa Diallo', $user->name);
        $this->assertSame(UserRole::Viewer, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check($password, $user->password));

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => $password,
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('users.index'))->assertForbidden();
    }

    public function test_registration_rejects_an_existing_email_and_invalid_passwords(): void
    {
        $validPassword = Str::random(20).'Aa1!';
        $shortPassword = Str::random(4).'1!';
        $differentPassword = Str::random(20).'Bb2!';
        User::factory()->create(['email' => 'existing@example.test']);

        $this->from(route('register'))->post(route('register.store'), [
            'first_name' => 'Test',
            'last_name' => 'Personne',
            'email' => 'existing@example.test',
            'password' => $validPassword,
            'password_confirmation' => $validPassword,
        ])->assertRedirect(route('register'))
            ->assertSessionHasErrors('email');

        $this->from(route('register'))->post(route('register.store'), [
            'first_name' => 'Test',
            'last_name' => 'Personne',
            'email' => 'new@example.test',
            'password' => $shortPassword,
            'password_confirmation' => $shortPassword,
        ])->assertRedirect(route('register'))
            ->assertSessionHasErrors('password');

        $this->from(route('register'))->post(route('register.store'), [
            'first_name' => 'Test',
            'last_name' => 'Personne',
            'email' => 'new@example.test',
            'password' => $validPassword,
            'password_confirmation' => $differentPassword,
        ])->assertRedirect(route('register'))
            ->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_public_registration_can_be_disabled_without_changing_routes(): void
    {
        config()->set('registration.public_enabled', false);
        $password = Str::random(20).'Aa1!';

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee(route('register'));
        $this->get(route('register'))->assertNotFound();
        $this->post(route('register.store'), [
            'first_name' => 'Test',
            'last_name' => 'Personne',
            'email' => 'disabled@example.test',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertForbidden();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_is_rate_limited_per_ip(): void
    {
        $password = Str::random(20).'Aa1!';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('register.store'), [
                'first_name' => 'Test',
                'last_name' => 'Personne',
                'email' => "personne{$attempt}@example.test",
                'password' => $password,
                'password_confirmation' => $password,
            ])->assertRedirect(route('login'));
        }

        $this->post(route('register.store'), [
            'first_name' => 'Test',
            'last_name' => 'Personne',
            'email' => 'personne6@example.test',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertStatus(429);

        $this->assertDatabaseCount('users', 5);
    }
}
