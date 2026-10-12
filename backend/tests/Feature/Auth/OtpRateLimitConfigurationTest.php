<?php

namespace Tests\Feature\Auth;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class OtpRateLimitConfigurationTest extends TestCase
{
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
}
