<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebSessionRotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_regenerates_web_session_id(): void
    {
        Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Session Rotation User',
            'phone' => '60123456789',
            'email' => 'rotation@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $session = $this->app['session.store'];
        $session->start();

        $originalSessionId = $session->getId();

        $response = $this->postJson('/web/auth/login', [
            'phone' => '60123456789',
            'password' => 'Password1',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('data.session.token');

        $rotatedSessionId = $session->getId();

        $this->assertNotSame(
            $originalSessionId,
            $rotatedSessionId,
            'Successful login must regenerate the Laravel session ID.'
        );

        $this->assertNotSame('', $rotatedSessionId);

        $this->assertNotNull(
            $session->get(
                'super_runner.web_session_token'
            )
        );
    }
}
