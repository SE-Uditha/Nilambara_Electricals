<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_renders_a_csrf_protected_form(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="_token"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertDontSee('href="/register"', false);
    }

    public function test_users_can_log_in_and_the_session_id_is_regenerated(): void
    {
        $user = User::factory()->create();

        $this->get(route('login'));
        $oldSessionId = session()->getId();

        $this->withSession(['preserved' => 'session value'])
            ->post(route('login.store'), [
                'email' => strtoupper($user->email),
                'password' => 'test-password-123',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('preserved', 'session value')
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSessionId, session()->getId());
    }

    public function test_users_cannot_log_in_with_an_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])->assertRedirect(route('login'))->assertSessionHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_login_requires_an_email_and_password(): void
    {
        $this->post(route('login.store'), [])
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_login_rejects_non_string_credentials(): void
    {
        $this->post(route('login.store'), [
            'email' => ['invalid'],
            'password' => ['invalid'],
        ])->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_attempts_per_email_and_ip(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'incorrect-password',
            ])->assertSessionHasErrors(['email']);
        }

        $this->post(route('login.store'), [
            'email' => strtoupper($user->email),
            'password' => 'test-password-123',
        ])->assertTooManyRequests();

        $this->assertGuest();

        $this->travel(61)->seconds();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'test-password-123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_invalidates_the_session_and_regenerates_the_csrf_token(): void
    {
        $user = User::factory()->create();

        $this->get(route('login'));
        $this->withSession(['private-data' => 'must be cleared'])
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'test-password-123',
            ]);

        $oldSessionId = session()->getId();
        $oldToken = session()->token();

        $this->post(route('logout'))
            ->assertRedirect('/')
            ->assertSessionMissing('private-data');

        $this->assertGuest();
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertNotSame($oldToken, session()->token());
    }

    public function test_login_rejects_requests_without_a_csrf_token_when_verification_is_active(): void
    {
        $user = User::factory()->create();

        // Laravel bypasses request forgery checks in its testing environment.
        $this->app->instance('env', 'production');

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'test-password-123',
        ])->assertStatus(419);

        $this->assertGuest();

        $this->withSession(['_token' => 'valid-test-csrf-token'])
            ->post(route('login.store'), [
                '_token' => 'valid-test-csrf-token',
                'email' => $user->email,
                'password' => 'test-password-123',
            ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_unused_authentication_features_are_not_exposed(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([
            '/forgot-password', '/reset-password/token', '/email/verify',
            '/two-factor-challenge', '/user/confirm-password',
            '/user/confirmed-password-status', '/passkeys/login/options',
        ] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_public_registration_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();

        $this->post('/register', [
            'name' => 'Uninvited User',
            'email' => 'uninvited@example.test',
            'password' => 'test-password-123',
            'password_confirmation' => 'test-password-123',
        ])->assertNotFound();

        $this->assertDatabaseCount('users', 0);
    }
}
