<?php

namespace Tests\Feature\Runner;

use App\Http\Controllers\Api\RunnerPublicProfileController;
use App\Models\Account;
use App\Models\RunnerPublicProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerPublicProfileLocationTest extends TestCase
{
    use RefreshDatabase;

    private function createApprovedRunner(): Account
    {
        $account = Account::query()->create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Runner Location Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $account->runner_status = Account::RUNNER_STATUS_APPROVED;
        $account->save();

        return $account->fresh();
    }

    private function makeAuthenticatedRequest(
        Account $account,
        array $data
    ): Request {
        $request = Request::create(
            '/api/me/runner-profile',
            'POST',
            $data
        );

        $request->attributes->set('account', $account);

        return $request;
    }

    private function createPublicProfile(
        Account $account,
        string $slug,
        ?string $baseLocation,
        ?string $serviceArea
    ): RunnerPublicProfile {
        return RunnerPublicProfile::query()->create([
            'profile_id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'public_slug' => $slug,
            'bio' => null,
            'base_location' => $baseLocation,
            'service_area' => $serviceArea,
            'services' => [],
            'is_published' => true,
            'views_count' => 0,
        ]);
    }

    public function test_runner_can_save_base_and_operating_area(): void
    {
        $account = $this->createApprovedRunner();

        $response = app(RunnerPublicProfileController::class)->save(
            $this->makeAuthenticatedRequest($account, [
                'public_slug' => 'runner-location-test',
                'base_location' => 'Tuaran',
                'service_area' => 'Tuaran - Kota Kinabalu sahaja',
                'is_published' => true,
            ])
        );

        $this->assertSame(201, $response->getStatusCode());

        $this->assertDatabaseHas('runner_public_profiles', [
            'account_id' => $account->id,
            'public_slug' => 'runner-location-test',
            'base_location' => 'Tuaran',
            'service_area' => 'Tuaran - Kota Kinabalu sahaja',
            'is_published' => true,
        ]);
    }

    public function test_runner_can_use_state_and_entire_state_as_location_text(): void
    {
        $account = $this->createApprovedRunner();

        $response = app(RunnerPublicProfileController::class)->save(
            $this->makeAuthenticatedRequest($account, [
                'public_slug' => 'runner-sabah-location',
                'base_location' => 'Sabah',
                'service_area' => 'Seluruh Sabah',
                'is_published' => true,
            ])
        );

        $this->assertSame(201, $response->getStatusCode());

        $this->assertDatabaseHas('runner_public_profiles', [
            'account_id' => $account->id,
            'base_location' => 'Sabah',
            'service_area' => 'Seluruh Sabah',
        ]);
    }

    public function test_public_profile_displays_only_base_and_operating_area(): void
    {
        $account = $this->createApprovedRunner();

        $this->createPublicProfile(
            $account,
            'runner-location-public',
            'Tuaran',
            'Tuaran - Kota Kinabalu sahaja'
        );

        $response = app(RunnerPublicProfileController::class)
            ->show('runner-location-public');

        $this->assertSame(200, $response->getStatusCode());

        $profile = $response->getData(true)['data']['profile'];

        $this->assertSame('Tuaran', $profile['base_location']);

        $this->assertSame(
            'Tuaran - Kota Kinabalu sahaja',
            $profile['service_area']
        );

        $this->assertSame(
            'Runner Location Test',
            $profile['profile_name']
        );

        $this->assertArrayNotHasKey('latitude', $profile);
        $this->assertArrayNotHasKey('longitude', $profile);
        $this->assertArrayNotHasKey('verified_name', $profile);
    }

    public function test_public_profile_does_not_expose_third_location_field(): void
    {
        $account = $this->createApprovedRunner();

        $this->createPublicProfile(
            $account,
            'runner-two-location-fields',
            'Tuaran',
            'Tuaran dan Tamparuli'
        );

        $response = app(RunnerPublicProfileController::class)
            ->show('runner-two-location-fields');

        $this->assertSame(200, $response->getStatusCode());

        $profile = $response->getData(true)['data']['profile'];

        $this->assertSame('Tuaran', $profile['base_location']);

        $this->assertSame(
            'Tuaran dan Tamparuli',
            $profile['service_area']
        );
    }

    public function test_runner_can_update_base_without_changing_operating_area(): void
    {
        $account = $this->createApprovedRunner();
        $controller = app(RunnerPublicProfileController::class);

        $createResponse = $controller->save(
            $this->makeAuthenticatedRequest($account, [
                'public_slug' => 'runner-location-update',
                'base_location' => 'Tuaran',
                'service_area' => 'Tuaran - Kota Kinabalu sahaja',
                'is_published' => true,
            ])
        );

        $this->assertSame(201, $createResponse->getStatusCode());

        $updateResponse = $controller->save(
            $this->makeAuthenticatedRequest($account, [
                'base_location' => 'Sabah',
            ])
        );

        $this->assertSame(200, $updateResponse->getStatusCode());

        $this->assertDatabaseHas('runner_public_profiles', [
            'account_id' => $account->id,
            'public_slug' => 'runner-location-update',
            'base_location' => 'Sabah',
            'service_area' => 'Tuaran - Kota Kinabalu sahaja',
        ]);
    }

    public function test_runner_can_update_operating_area_without_changing_base(): void
    {
        $account = $this->createApprovedRunner();
        $controller = app(RunnerPublicProfileController::class);

        $createResponse = $controller->save(
            $this->makeAuthenticatedRequest($account, [
                'public_slug' => 'runner-operating-area-update',
                'base_location' => 'Tuaran',
                'service_area' => 'Tuaran sahaja',
                'is_published' => true,
            ])
        );

        $this->assertSame(201, $createResponse->getStatusCode());

        $updateResponse = $controller->save(
            $this->makeAuthenticatedRequest($account, [
                'service_area' => 'Seluruh Sabah',
            ])
        );

        $this->assertSame(200, $updateResponse->getStatusCode());

        $this->assertDatabaseHas('runner_public_profiles', [
            'account_id' => $account->id,
            'public_slug' => 'runner-operating-area-update',
            'base_location' => 'Tuaran',
            'service_area' => 'Seluruh Sabah',
        ]);
    }

    public function test_updating_other_profile_fields_preserves_both_location_fields(): void
    {
        $account = $this->createApprovedRunner();
        $controller = app(RunnerPublicProfileController::class);

        $createResponse = $controller->save(
            $this->makeAuthenticatedRequest($account, [
                'public_slug' => 'runner-location-preserve',
                'base_location' => 'Tuaran',
                'service_area' => 'Tuaran - Kota Kinabalu sahaja',
                'is_published' => true,
            ])
        );

        $this->assertSame(201, $createResponse->getStatusCode());

        $updateResponse = $controller->save(
            $this->makeAuthenticatedRequest($account, [
                'bio' => 'Runner menyediakan perkhidmatan penghantaran.',
            ])
        );

        $this->assertSame(200, $updateResponse->getStatusCode());

        $this->assertDatabaseHas('runner_public_profiles', [
            'account_id' => $account->id,
            'public_slug' => 'runner-location-preserve',
            'base_location' => 'Tuaran',
            'service_area' => 'Tuaran - Kota Kinabalu sahaja',
        ]);
    }

    public function test_private_profile_payload_does_not_expose_third_location_field(): void
    {
        $account = $this->createApprovedRunner();

        $this->createPublicProfile(
            $account,
            'runner-private-payload',
            'Tuaran',
            'Seluruh Sabah'
        );

        $response = app(RunnerPublicProfileController::class)->save(
            $this->makeAuthenticatedRequest($account, [
                'bio' => 'Profil Runner.',
            ])
        );

        $this->assertSame(200, $response->getStatusCode());

        $profile = $response->getData(true)['data']['profile'];

        $this->assertSame('Tuaran', $profile['base_location']);
        $this->assertSame('Seluruh Sabah', $profile['service_area']);
        $this->assertSame(
            'Runner Location Test',
            $profile['profile_name']
        );
        $this->assertArrayNotHasKey('verified_name', $profile);
    }
}
