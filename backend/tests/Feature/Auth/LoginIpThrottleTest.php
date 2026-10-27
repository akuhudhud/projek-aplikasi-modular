<?php

namespace Tests\Feature\Auth;

use Illuminate\Cache\RateLimiter;
use Tests\TestCase;

class LoginIpThrottleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(RateLimiter::class)->clear('login-ip:127.0.0.1');
    }

    public function test_login_is_throttled_after_ten_requests_from_same_ip(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->withServerVariables([
                'REMOTE_ADDR' => '127.0.0.1',
            ])->postJson('/api/login', [
                'email' => 'unknown@example.com',
                'password' => 'WrongPass1',
            ])->assertStatus(401);
        }

        $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
        ])->postJson('/api/login', [
            'email' => 'unknown@example.com',
            'password' => 'WrongPass1',
        ])->assertStatus(429);
    }
}
