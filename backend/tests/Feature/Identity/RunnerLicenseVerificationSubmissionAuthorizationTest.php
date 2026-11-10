<?php

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunnerLicenseVerificationSubmissionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_standalone_license_submission_endpoint_is_removed(): void
    {
        $this->postJson(
            '/api/me/runner-license-verification',
            [
                'license_number' => 'D123456789012',
                'license_type' => 'GENERAL',
                'license_class' => 'D',
                'expires_at' => now()->addYear()->toDateString(),
            ]
        )->assertNotFound();

        $this->assertDatabaseCount(
            'runner_license_verifications',
            0
        );
    }
}
