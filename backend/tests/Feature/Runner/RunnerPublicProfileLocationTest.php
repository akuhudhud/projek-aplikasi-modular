<?php

namespace Tests\Feature\Runner;

use App\Http\Controllers\Api\RunnerPublicProfileController;
use App\Models\Account;
use App\Models\RunnerPublicProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RunnerPublicProfileLocationTest extends TestCase
{
    use RefreshDatabase;

    private function createApprovedRunner(): Account
    {
        $account = Account::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Runner Location Test',
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

    private function createLegacyPublicProfile(
        Account $account,
        string $slug,
        ?string $baseLocation
    ): RunnerPublicProfile {
        return RunnerPublicProfile::query()->create([
            'profile_id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'public_slug' => $slug,
            'display_name' => 'Legacy Runner',
            'bio' => null,
            'service_area' => 'Tuaran',
            'base_location' => $baseLocation,
            'coverage_area' => 'Tuaran dan Tamparuli',
            'services' => [],
            'is_published' => true,
            'views_count' => 0,
        ]);
    }

    public function test_runner_can_save_base_location_and_coverage_area(): void
    {
        $account = $this->createApprovedRunner();
        $controller = app(RunnerPublicProfileController::class);

        $response = $controller->save(
            $this->makeAuthenticatedRequest($account, [
                'public_slug' => 'runner-location-test',
                'display_name' => 'Runner Location Test',
                'base_location' => 'Tuaran, Sabah',
                'coverage_area' => 'Tuaran, Tamparuli dan Kota Kinabalu',
                'is_published' => true,
            ])
        );

        $this->assertSame(201, $response->getStatusCode());

        $this->assertDatabaseHas('runner_public_profiles', [
            'account_id' => $account->id,
            'public_slug' => 'runner-location-test',
            'base_location' => 'Tuaran, Sabah',
            'coverage_area' => 'Tuaran, Tamparuli dan Kota Kinabalu',
            'is_published' => true,
        ]);
    }

    public function test_public_profile_displays_base_location_and_coverage_area(): void
    {
        $account = $this->createApprovedRunner();
        $controller = app(RunnerPublicProfileController::class);

        $saveResponse = $controller->save(
            $this->makeAuthenticatedRequest($account, [
                'public_slug' => 'runner-location-public',
                'display_name' => 'Runner Location Public',
                'base_location' => 'Tuaran, Sabah',
                'coverage_area' => 'Tuaran dan Tamparuli',
                'is_published' => true,
            ])
        );

        $this->assertSame(201, $saveResponse->getStatusCode());

        $response = $controller->show('runner-location-public');

        $this->assertSame(200, $response->getStatusCode());

        $payload = $response->getData(true);
        $profile = $payload['data']['profile'];

        $this->assertSame('Tuaran, Sabah', $profile['base_location']);
        $this->assertSame('Tuaran dan Tamparuli', $profile['coverage_area']);

        $this->assertArrayNotHasKey('latitude', $profile);
        $this->assertArrayNotHasKey('longitude', $profile);
    }

    public function test_public_profile_hides_unsafe_legacy_base_locations(): void
    {
        $unsafeLocations = [
            '5.9804, 116.0735',
            'Jalan Tuaran, Kota Kinabalu',
            'Tuaran 89200, Sabah',
        ];

        foreach ($unsafeLocations as $index => $unsafeLocation) {
            $account = $this->createApprovedRunner();
            $slug = 'legacy-location-' . $index;

            // Insert directly to simulate records saved before validation existed.
            $this->createLegacyPublicProfile(
                $account,
                $slug,
                $unsafeLocation
            );

            $response = app(RunnerPublicProfileController::class)
                ->show($slug);

            $this->assertSame(200, $response->getStatusCode());

            $profile = $response->getData(true)['data']['profile'];

            $this->assertNull(
                $profile['base_location'],
                'Unsafe legacy location must not be exposed publicly: '
                    . $unsafeLocation
            );

            $this->assertSame(
                'Legacy Runner',
                $profile['display_name']
            );

            $this->assertSame(
                'Tuaran dan Tamparuli',
                $profile['coverage_area']
            );
        }
    }

    public function test_public_profile_keeps_safe_legacy_administrative_area(): void
    {
        $account = $this->createApprovedRunner();

        $this->createLegacyPublicProfile(
            $account,
            'legacy-safe-location',
            'Tuaran, Sabah'
        );

        $response = app(RunnerPublicProfileController::class)
            ->show('legacy-safe-location');

        $this->assertSame(200, $response->getStatusCode());

        $profile = $response->getData(true)['data']['profile'];

        $this->assertSame(
            'Tuaran, Sabah',
            $profile['base_location']
        );
    }

    public function test_base_location_rejects_coordinate_pairs(): void
    {
        $account = $this->createApprovedRunner();
        $controller = app(RunnerPublicProfileController::class);

        $this->expectException(ValidationException::class);

        $controller->save(
            $this->makeAuthenticatedRequest($account, [
                'public_slug' => 'runner-coordinate-test',
                'display_name' => 'Runner Coordinate Test',
                'base_location' => '5.9804, 116.0735',
                'is_published' => true,
            ])
        );
    }

    public function test_base_location_rejects_detailed_street_address(): void
    {
        $account = $this->createApprovedRunner();
        $controller = app(RunnerPublicProfileController::class);

        $this->expectException(ValidationException::class);

        $controller->save(
            $this->makeAuthenticatedRequest($account, [
                'public_slug' => 'runner-address-test',
                'display_name' => 'Runner Address Test',
                'base_location' => 'Jalan Tuaran, Kota Kinabalu',
                'is_published' => true,
            ])
        );
    }

    public function test_base_location_rejects_postcode(): void
    {
        $account = $this->createApprovedRunner();
        $controller = app(RunnerPublicProfileController::class);

        $this->expectException(ValidationException::class);

        $controller->save(
            $this->makeAuthenticatedRequest($account, [
                'public_slug' => 'runner-postcode-test',
                'display_name' => 'Runner Postcode Test',
                'base_location' => 'Tuaran 89200, Sabah',
                'is_published' => true,
            ])
        );
    }

    public function test_updating_other_profile_fields_preserves_location_fields(): void
    {
        $account = $this->createApprovedRunner();
        $controller = app(RunnerPublicProfileController::class);

        $createResponse = $controller->save(
            $this->makeAuthenticatedRequest($account, [
                'public_slug' => 'runner-location-update',
                'display_name' => 'Runner Location Update',
                'base_location' => 'Tuaran, Sabah',
                'coverage_area' => 'Tuaran dan Tamparuli',
                'is_published' => true,
            ])
        );

        $this->assertSame(201, $createResponse->getStatusCode());

        $updateResponse = $controller->save(
            $this->makeAuthenticatedRequest($account, [
                'display_name' => 'Runner Updated Name',
            ])
        );

        $this->assertSame(200, $updateResponse->getStatusCode());

        $this->assertDatabaseHas('runner_public_profiles', [
            'account_id' => $account->id,
            'public_slug' => 'runner-location-update',
            'display_name' => 'Runner Updated Name',
            'base_location' => 'Tuaran, Sabah',
            'coverage_area' => 'Tuaran dan Tamparuli',
        ]);
    }
}
