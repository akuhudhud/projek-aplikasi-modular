<?php

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RunnerLicenseVerificationSubmissionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_unauthenticated_user_cannot_submit_runner_license_verification(): void
    {
        $response = $this->post(
            '/api/me/runner-license-verification',
            [
                'license_number' => 'D123456789012',
                'license_type' => 'GENERAL',
                'license_class' => 'D',
                'expires_at' => now()->addYear()->toDateString(),
                'document' => UploadedFile::fake()->image(
                    'runner-license.jpg'
                ),
            ]
        );

        $response->assertUnauthorized();

        $this->assertDatabaseCount(
            'runner_license_verifications',
            0
        );

        $this->assertSame(
            [],
            Storage::disk('local')->allFiles(
                'runner-license-documents'
            )
        );
    }
}
