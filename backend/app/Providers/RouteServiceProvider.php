<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/home';

    private function diagnostic(string $message): void
    {
        fwrite(STDERR, '[ROUTE_BOOT] '.$message.PHP_EOL);
        fflush(STDERR);
    }

    public function boot(): void
    {
        $this->diagnostic('BOOT_START');

        $this->diagnostic('RATE_LIMITER_API_START');
        RateLimiter::for('api', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(60)->by($request->ip());
        });
        $this->diagnostic('RATE_LIMITER_API_DONE');

        $this->diagnostic('RATE_LIMITER_LOGIN_START');
        RateLimiter::for('login-ip', function (Request $request) {
            return Limit::perMinute(10)->by('login-ip:'.$request->ip());
        });
        $this->diagnostic('RATE_LIMITER_LOGIN_DONE');

        $this->diagnostic('RATE_LIMITER_OTP_REQUEST_IP_START');
        RateLimiter::for('otp-request-ip', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(10)->by('otp-request-ip:'.$request->ip());
        });
        $this->diagnostic('RATE_LIMITER_OTP_REQUEST_IP_DONE');

        $this->diagnostic('RATE_LIMITER_OTP_REQUEST_CONTACT_START');
        RateLimiter::for('otp-request-contact', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            $channel = strtolower(trim((string) $request->input('channel')));
            $contact = strtolower(trim((string) $request->input('contact')));

            return Limit::perMinutes(10, 6)->by(
                'otp-request-contact:'.hash('sha256', $channel.'|'.$contact)
            );
        });
        $this->diagnostic('RATE_LIMITER_OTP_REQUEST_CONTACT_DONE');

        $this->diagnostic('RATE_LIMITER_OTP_VERIFY_IP_START');
        RateLimiter::for('otp-verify-ip', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(20)->by('otp-verify-ip:'.$request->ip());
        });
        $this->diagnostic('RATE_LIMITER_OTP_VERIFY_IP_DONE');

        $this->diagnostic('RATE_LIMITER_OTP_VERIFY_ID_START');
        RateLimiter::for('otp-verify-id', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            $verificationId = trim(
                (string) $request->input('verification_id')
            );

            return Limit::perMinutes(5, 5)->by(
                'otp-verify-id:'.hash('sha256', $verificationId)
            );
        });
        $this->diagnostic('RATE_LIMITER_OTP_VERIFY_ID_DONE');

        $this->diagnostic('RATE_LIMITER_REGISTRATION_IP_START');
        RateLimiter::for('registration-complete-ip', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(5)->by(
                'registration-complete-ip:'.$request->ip()
            );
        });
        $this->diagnostic('RATE_LIMITER_REGISTRATION_IP_DONE');

        $this->diagnostic('RATE_LIMITER_REGISTRATION_TOKEN_START');
        RateLimiter::for('registration-complete-token', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            $token = (string) $request->input('verification_token');

            return Limit::perMinute(5)->by(
                'registration-complete-token:'.hash('sha256', $token)
            );
        });
        $this->diagnostic('RATE_LIMITER_REGISTRATION_TOKEN_DONE');

        $this->diagnostic('ROUTES_CALLBACK_START');

        $this->routes(function () {
            fwrite(STDERR, "[ROUTE_BOOT] API_FILE_START\n");
            fflush(STDERR);

            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            fwrite(STDERR, "[ROUTE_BOOT] API_FILE_DONE\n");
            fflush(STDERR);

            fwrite(STDERR, "[ROUTE_BOOT] WEB_FILE_START\n");
            fflush(STDERR);

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            fwrite(STDERR, "[ROUTE_BOOT] WEB_FILE_DONE\n");
            fflush(STDERR);
        });

        $this->diagnostic('ROUTES_CALLBACK_DONE');
        $this->diagnostic('BOOT_DONE');
    }
}
