<?php

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunnerLicenseDecisionConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_license_decision_endpoint_is_unavailable(): void
    {
        $verificationId = '33333333-3333-4333-8333-333333333333';

        $this->postJson(
            '/api/admin/runner-license-verifications/'
                .$verificationId.'/decision',
            [
                'decision' => 'VERIFIED',
            ]
        )->assertNotFound();

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
