<?php

namespace Tests\Feature\Identity;

use Tests\TestCase;

class RunnerLicenseVerificationListApiTest extends TestCase
{
    public function test_standalone_admin_license_list_endpoint_is_removed(): void
    {
        $this->getJson('/api/admin/runner-license-verifications')
            ->assertNotFound();
    }
}
