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

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('otp-request-ip', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(10)
                ->by('otp-request-ip:'.$request->ip());
        });

        RateLimiter::for('otp-request-contact', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            $channel = strtolower(trim((string) $request->input('channel')));
            $contact = strtolower(trim((string) $request->input('contact')));

            return Limit::perMinutes(10, 6)
                ->by('otp-request-contact:'.hash(
                    'sha256',
                    $channel.'|'.$contact
                ));
        });

        RateLimiter::for('otp-verify-ip', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(20)
                ->by('otp-verify-ip:'.$request->ip());
        });

        RateLimiter::for('otp-verify-id', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            $verificationId = trim(
                (string) $request->input('verification_id')
            );

            return Limit::perMinutes(5, 5)
                ->by('otp-verify-id:'.hash(
                    'sha256',
                    $verificationId
                ));
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
