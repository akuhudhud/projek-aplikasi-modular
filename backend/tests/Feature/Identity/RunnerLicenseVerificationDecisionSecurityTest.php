<?php

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunnerLicenseVerificationDecisionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_standalone_license_decision_endpoint_is_removed(): void
    {
        $verificationId = '11111111-1111-4111-8111-111111111111';

        $this->postJson(
            '/api/admin/runner-license-verifications/'
                .$verificationId.'/decision',
            [
                'decision' => 'VERIFIED',
            ]
        )->assertNotFound();
    }

    public function test_standalone_license_rejection_endpoint_is_removed(): void
    {
        $verificationId = '22222222-2222-4222-8222-222222222222';

        $this->postJson(
            '/api/admin/runner-license-verifications/'
                .$verificationId.'/decision',
            [
                'decision' => 'REJECTED',
                'rejection_reason' => 'Dokumen tidak sah.',
            ]
        )->assertNotFound();
    }
}
