<?php

namespace Tests\Feature\Runner;

use App\Http\Controllers\Api\RunnerPublicProfileController;
use App\Models\Account;
use App\Models\RunnerPublicProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerPublicProfileOwnProfileTest extends TestCase
{
    use RefreshDatabase;

    private function createRunner(
        string $status = 'ACTIVE',
        string $runnerStatus = Account::RUNNER_STATUS_APPROVED
    ): Account {
        $account = Account::query()->create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Runner Own Profile Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => $status,
        ]);

        $account->runner_status = $runnerStatus;
        $account->save();

        return $account->fresh();
    }

    private function makeRequest(Account $account): Request
    {
        $request = Request::create(
            '/web/runner/profile',
            'GET'
        );

        $request->attributes->set('account', $account);

        return $request;
    }

    private function createProfile(Account $account): RunnerPublicProfile
    {
        return RunnerPublicProfile::query()->create([
            'profile_id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'public_slug' => 'runner-own-profile-test',
            'bio' => 'Runner menyediakan perkhidmatan penghantaran.',
            'base_location' => 'Tuaran',
            'service_area' => 'Tuaran dan Tamparuli',
            'services' => ['Penghantaran barang'],
            'is_published' => true,
            'views_count' => 12,
        ]);
    }

    public function test_approved_runner_can_get_own_profile(): void
    {
        $account = $this->createRunner();
        $this->createProfile($account);

        $response = app(RunnerPublicProfileController::class)
            ->showMine($this->makeRequest($account));

        $this->assertSame(200, $response->getStatusCode());

        $profile = $response->getData(true)['data']['profile'];

        $this->assertSame(
            'runner-own-profile-test',
            $profile['public_slug']
        );

        $this->assertSame(
            'Runner Own Profile Test',
            $profile['profile_name']
        );

        $this->assertSame(
            'Runner menyediakan perkhidmatan penghantaran.',
            $profile['bio']
        );

        $this->assertSame('Tuaran', $profile['base_location']);

        $this->assertSame(
            'Tuaran dan Tamparuli',
            $profile['service_area']
        );

        $this->assertSame(
            ['Penghantaran barang'],
            $profile['services']
        );

        $this->assertTrue($profile['is_published']);
        $this->assertSame(12, $profile['views_count']);

        $this->assertArrayNotHasKey('verified_name', $profile);
        $this->assertArrayNotHasKey('mykad_number', $profile);
        $this->assertArrayNotHasKey('latitude', $profile);
        $this->assertArrayNotHasKey('longitude', $profile);
    }

    public function test_approved_runner_without_profile_gets_null_profile(): void
    {
        $account = $this->createRunner();

        $response = app(RunnerPublicProfileController::class)
            ->showMine($this->makeRequest($account));

        $this->assertSame(200, $response->getStatusCode());

        $this->assertNull(
            $response->getData(true)['data']['profile']
        );
    }

    public function test_pending_runner_cannot_get_own_profile(): void
    {
        $account = $this->createRunner(
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $response = app(RunnerPublicProfileController::class)
            ->showMine($this->makeRequest($account));

        $this->assertSame(403, $response->getStatusCode());

        $this->assertFalse(
            $response->getData(true)['success']
        );
    }

    public function test_inactive_runner_cannot_get_own_profile(): void
    {
        $account = $this->createRunner(
            'SUSPENDED',
            Account::RUNNER_STATUS_APPROVED
        );

        $response = app(RunnerPublicProfileController::class)
            ->showMine($this->makeRequest($account));

        $this->assertSame(403, $response->getStatusCode());

        $this->assertFalse(
            $response->getData(true)['success']
        );
    }
}
