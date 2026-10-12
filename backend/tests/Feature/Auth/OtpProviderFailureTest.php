<?php

namespace Tests\Feature\Auth;

use App\Contracts\OtpProviderInterface;
use App\Exceptions\OtpDeliveryException;
use App\Models\RegistrationVerification;
use App\Services\Otp\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\TestCase;

class OtpProviderFailureTest extends TestCase
{
    use RefreshDatabase;

    private function bindFailingOtpProvider(): void
    {
        $this->app->instance(
            OtpProviderInterface::class,
            new class implements OtpProviderInterface {
                public function send(
                    string $channel,
                    string $contact,
                    string $purpose,
                    string $otp
                ): void {
                    throw new RuntimeException(
                        'Simulated OTP delivery failure.'
                    );
                }
            }
        );
    }

    public function test_failed_initial_delivery_does_not_create_verification(): void
    {
        $this->bindFailingOtpProvider();

        try {
            app(OtpService::class)->request(
                'phone',
                '60123456789',
                'REGISTRATION'
            );

            $this->fail('Expected OTP delivery to fail.');
        } catch (OtpDeliveryException $exception) {
            $this->assertSame(
                'OTP delivery failed.',
                $exception->getMessage()
            );

            $this->assertSame(
                'Simulated OTP delivery failure.',
                $exception->getPrevious()?->getMessage()
            );
        }

        $this->assertDatabaseCount(
            'registration_verifications',
            0
        );
    }

    public function test_failed_resend_preserves_existing_verification(): void
    {
        $originalTime = Carbon::parse('2026-10-12 10:00:00');

        Carbon::setTestNow($originalTime);

        try {
            $verification = app(OtpService::class)->request(
                'phone',
                '60123456789',
                'REGISTRATION'
            );

            $originalOtpHash = $verification->otp_hash;
            $originalExpiry = $verification->expires_at
                ->toDateTimeString();
            $originalLastSentAt = $verification->last_sent_at
                ->toDateTimeString();
            $originalResendCount = $verification->resend_count;

            Carbon::setTestNow($originalTime->copy()->addSeconds(61));

            $this->bindFailingOtpProvider();

            try {
                app(OtpService::class)->request(
                    'phone',
                    '60123456789',
                    'REGISTRATION'
                );

                $this->fail('Expected OTP resend to fail.');
            } catch (OtpDeliveryException $exception) {
                $this->assertSame(
                    'OTP delivery failed.',
                    $exception->getMessage()
                );

                $this->assertSame(
                    'Simulated OTP delivery failure.',
                    $exception->getPrevious()?->getMessage()
                );
            }

            $verification->refresh();

            $this->assertSame(
                $originalOtpHash,
                $verification->otp_hash
            );

            $this->assertSame(
                $originalExpiry,
                $verification->expires_at->toDateTimeString()
            );

            $this->assertSame(
                $originalLastSentAt,
                $verification->last_sent_at->toDateTimeString()
            );

            $this->assertSame(
                $originalResendCount,
                $verification->resend_count
            );

            $this->assertSame(
                1,
                RegistrationVerification::query()->count()
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_otp_request_endpoint_returns_safe_error_when_delivery_fails(): void
    {
        $this->bindFailingOtpProvider();

        $response = $this->postJson('/api/otp/request', [
            'channel' => 'phone',
            'contact' => '60123456789',
            'purpose' => 'REGISTRATION',
        ]);

        $response
            ->assertStatus(503)
            ->assertJson([
                'success' => false,
                'message' => 'OTP tidak dapat dihantar buat masa ini. Sila cuba semula.',
            ])
            ->assertJsonMissing([
                'message' => 'Simulated OTP delivery failure.',
            ]);

        $this->assertDatabaseCount(
            'registration_verifications',
            0
        );
    }
}
