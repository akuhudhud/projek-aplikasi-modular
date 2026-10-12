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
        $this->assertNotNull(
            RateLimiter::limiter('otp-request-ip')
        );

        $this->assertNotNull(
            RateLimiter::limiter('otp-request-contact')
        );

        $this->assertNotNull(
            RateLimiter::limiter('otp-verify-ip')
        );

        $this->assertNotNull(
            RateLimiter::limiter('otp-verify-id')
        );
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

        $this->assertContains(
            'throttle:otp-request-ip',
            $requestRoute->gatherMiddleware()
        );

        $this->assertContains(
            'throttle:otp-request-contact',
            $requestRoute->gatherMiddleware()
        );

        $this->assertContains(
            'throttle:otp-verify-ip',
            $verifyRoute->gatherMiddleware()
        );

        $this->assertContains(
            'throttle:otp-verify-id',
            $verifyRoute->gatherMiddleware()
        );
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
                    1,
                ],
                'otp-request-contact' => [
                    Request::create('/api/otp/request', 'POST', [
                        'channel' => 'phone',
                        'contact' => '60123456789',
                    ]),
                    6,
                    10,
                ],
                'otp-verify-ip' => [
                    Request::create('/api/otp/verify', 'POST'),
                    20,
                    1,
                ],
                'otp-verify-id' => [
                    Request::create('/api/otp/verify', 'POST', [
                        'verification_id' =>
                            '00000000-0000-0000-0000-000000000001',
                    ]),
                    5,
                    5,
                ],
            ];

            foreach ($policies as $name => [$request, $attempts, $minutes]) {
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
                    $minutes,
                    $limit->decayMinutes,
                    "Unexpected decay period for {$name}."
                );
            }
        } finally {
            app()->instance('env', $originalEnvironment);
        }
    }
}
