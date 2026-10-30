<?php

namespace Tests\Feature\Auth;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class OtpRateLimitConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_rate_limit_policies_are_registered(): void
    {
        foreach ([
            'otp-request-ip',
            'otp-request-contact',
            'otp-verify-ip',
            'otp-verify-id',
        ] as $name) {
            $this->assertNotNull(
                RateLimiter::limiter($name),
                "Limiter {$name} must be registered."
            );
        }
    }

    public function test_otp_routes_use_their_dedicated_rate_limiters(): void
    {
        $routes = collect(
            app('router')->getRoutes()->getRoutes()
        );

        $requestRoute = $routes->first(
            fn ($route) => $route->uri() === 'api/otp/request'
                && in_array('POST', $route->methods(), true)
        );

        $verifyRoute = $routes->first(
            fn ($route) => $route->uri() === 'api/otp/verify'
                && in_array('POST', $route->methods(), true)
        );

        $this->assertNotNull($requestRoute);
        $this->assertNotNull($verifyRoute);

        foreach ([
            'throttle:otp-request-ip',
            'throttle:otp-request-contact',
        ] as $middleware) {
            $this->assertContains(
                $middleware,
                $requestRoute->gatherMiddleware()
            );
        }

        foreach ([
            'throttle:otp-verify-ip',
            'throttle:otp-verify-id',
        ] as $middleware) {
            $this->assertContains(
                $middleware,
                $verifyRoute->gatherMiddleware()
            );
        }
    }

    public function test_otp_rate_limit_policies_have_expected_thresholds(): void
    {
        $originalEnvironment = app()->environment();

        try {
            app()->instance('env', 'production');

            $policies = [
                'otp-request-ip' => [
                    Request::create('/api/otp/request', 'POST'),
                    10,
                    60,
                ],
                'otp-request-contact' => [
                    Request::create('/api/otp/request', 'POST', [
                        'channel' => 'phone',
                        'contact' => '60123456789',
                    ]),
                    6,
                    600,
                ],
                'otp-verify-ip' => [
                    Request::create('/api/otp/verify', 'POST'),
                    20,
                    60,
                ],
                'otp-verify-id' => [
                    Request::create('/api/otp/verify', 'POST', [
                        'verification_id' =>
                            '00000000-0000-0000-0000-000000000001',
                    ]),
                    5,
                    300,
                ],
            ];

            foreach ($policies as $name => [$request, $attempts, $seconds]) {
                $limiter = RateLimiter::limiter($name);

                $this->assertNotNull($limiter);

                $limit = $limiter($request);

                $this->assertInstanceOf(
                    Limit::class,
                    $limit,
                    "Limiter {$name} must return a Limit."
                );

                $this->assertSame(
                    $attempts,
                    $limit->maxAttempts,
                    "Unexpected attempt threshold for {$name}."
                );

                $this->assertSame(
                    $seconds,
                    $limit->decaySeconds,
                    "Unexpected decay period for {$name}."
                );
            }
        } finally {
            app()->instance('env', $originalEnvironment);
        }
    }

    public function test_otp_request_is_limited_by_ip(): void
    {
        $originalEnvironment = $this->useProductionRateLimits();

        try {
            $this->withServerVariables([
                'REMOTE_ADDR' => '192.0.2.41',
            ]);

            for ($attempt = 1; $attempt <= 10; $attempt++) {
                $this->postJson('/api/otp/request', [
                    'channel' => 'phone',
                    'contact' => '6012345'.str_pad(
                        (string) $attempt,
                        4,
                        '0',
                        STR_PAD_LEFT
                    ),
                    'purpose' => 'INVALID_PURPOSE',
                ])->assertStatus(422);
            }

            $this->postJson('/api/otp/request', [
                'channel' => 'phone',
                'contact' => '60123459999',
                'purpose' => 'INVALID_PURPOSE',
            ])->assertStatus(429);
        } finally {
            $this->restoreEnvironment($originalEnvironment);
        }
    }

    public function test_otp_request_is_limited_by_contact(): void
    {
        $originalEnvironment = $this->useProductionRateLimits();

        try {
            $this->withServerVariables([
                'REMOTE_ADDR' => '192.0.2.42',
            ]);

            $payload = [
                'channel' => 'phone',
                'contact' => '60123456789',
                'purpose' => 'INVALID_PURPOSE',
            ];

            for ($attempt = 1; $attempt <= 6; $attempt++) {
                $this->postJson(
                    '/api/otp/request',
                    $payload
                )->assertStatus(422);
            }

            $this->postJson(
                '/api/otp/request',
                $payload
            )->assertStatus(429);
        } finally {
            $this->restoreEnvironment($originalEnvironment);
        }
    }

    public function test_otp_verification_is_limited_by_verification_id(): void
    {
        $originalEnvironment = $this->useProductionRateLimits();

        try {
            $this->withServerVariables([
                'REMOTE_ADDR' => '192.0.2.43',
            ]);

            $payload = [
                'verification_id' =>
                    '00000000-0000-0000-0000-000000000041',
                'otp' => '000000',
            ];

            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $this->postJson(
                    '/api/otp/verify',
                    $payload
                )->assertStatus(404);
            }

            $this->postJson(
                '/api/otp/verify',
                $payload
            )->assertStatus(429);
        } finally {
            $this->restoreEnvironment($originalEnvironment);
        }
    }

    public function test_otp_verification_is_limited_by_ip(): void
    {
        $originalEnvironment = $this->useProductionRateLimits();

        try {
            $this->withServerVariables([
                'REMOTE_ADDR' => '192.0.2.44',
            ]);

            for ($attempt = 1; $attempt <= 20; $attempt++) {
                $verificationId = sprintf(
                    '00000000-0000-0000-0000-%012d',
                    $attempt
                );

                $this->postJson('/api/otp/verify', [
                    'verification_id' => $verificationId,
                    'otp' => '000000',
                ])->assertStatus(404);
            }

            $this->postJson('/api/otp/verify', [
                'verification_id' =>
                    '00000000-0000-0000-0000-000000000099',
                'otp' => '000000',
            ])->assertStatus(429);
        } finally {
            $this->restoreEnvironment($originalEnvironment);
        }
    }

    private function useProductionRateLimits(): string
    {
        $originalEnvironment = app()->environment();

        app()->instance('env', 'production');

        return $originalEnvironment;
    }

    private function restoreEnvironment(string $environment): void
    {
        app()->instance('env', $environment);
    }
}
