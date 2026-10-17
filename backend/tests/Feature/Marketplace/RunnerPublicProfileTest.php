<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\RunnerPublicProfile;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerPublicProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_runner_can_publish_a_public_profile(): void
    {
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $token = $this->createSessionToken($runner);

        $response = $this->withToken($token)
            ->postJson('/api/me/runner-profile', [
                'public_slug' => 'runner-sabah-01',
                'display_name' => 'Runner Sabah',
                'bio' => 'Delivery services in my service area.',
                'service_area' => 'Tuaran',
                'services' => ['Parcel delivery', 'Document delivery'],
                'is_published' => true,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.profile.public_slug',
                'runner-sabah-01'
            )
            ->assertJsonPath('data.profile.is_published', true);

        $this->assertDatabaseHas('runner_public_profiles', [
            'account_id' => $runner->id,
            'public_slug' => 'runner-sabah-01',
            'is_published' => true,
        ]);
    }

    public function test_unapproved_account_cannot_publish_a_profile(): void
    {
        $account = $this->makeAccount(
            Account::RUNNER_STATUS_PENDING
        );

        $token = $this->createSessionToken($account);

        $this->withToken($token)
            ->postJson('/api/me/runner-profile', [
                'public_slug' => 'pending-runner',
                'display_name' => 'Pending Runner',
                'is_published' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('runner_public_profiles', 0);
    }

    public function test_public_profile_returns_only_public_fields_and_counts_views(): void
    {
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        RunnerPublicProfile::query()->create([
            'profile_id' => (string) Str::uuid(),
            'account_id' => $runner->id,
            'public_slug' => 'public-runner',
            'display_name' => 'Public Runner',
            'bio' => 'Available for deliveries.',
            'service_area' => 'Tuaran',
            'services' => ['Parcel delivery'],
            'is_published' => true,
            'views_count' => 0,
        ]);

        $this->getJson('/api/runners/public-runner')
            ->assertOk()
            ->assertJsonPath(
                'data.profile.display_name',
                'Public Runner'
            )
            ->assertJsonPath('data.profile.views_count', 1)
            ->assertJsonMissingPath('data.profile.account_id')
            ->assertJsonMissingPath('data.profile.phone')
            ->assertJsonMissingPath('data.profile.email')
            ->assertJsonMissingPath('data.profile.password');

        $this->assertDatabaseHas('runner_public_profiles', [
            'public_slug' => 'public-runner',
            'views_count' => 1,
        ]);
    }

    public function test_unpublished_profile_is_not_publicly_visible(): void
    {
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        RunnerPublicProfile::query()->create([
            'profile_id' => (string) Str::uuid(),
            'account_id' => $runner->id,
            'public_slug' => 'private-runner',
            'display_name' => 'Private Runner',
            'is_published' => false,
        ]);

        $this->getJson('/api/runners/private-runner')
            ->assertNotFound();
    }

    public function test_public_slug_must_be_unique_between_runners(): void
    {
        $first = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $second = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $token = $this->createSessionToken($second);

        RunnerPublicProfile::query()->create([
            'profile_id' => (string) Str::uuid(),
            'account_id' => $first->id,
            'public_slug' => 'taken-runner',
            'display_name' => 'First Runner',
            'is_published' => true,
        ]);

        $this->withToken($token)
            ->postJson('/api/me/runner-profile', [
                'public_slug' => 'taken-runner',
                'display_name' => 'Second Runner',
                'is_published' => true,
            ])
            ->assertUnprocessable();
    }

    private function makeAccount(
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => 'Runner Profile Test',
            'password' => bcrypt('TestPassword123'),
            'role' => 'USER',
            'status' => 'ACTIVE',
            'runner_status' => $runnerStatus,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return Account::query()->findOrFail($id);
    }

    private function createSessionToken(Account $account): string
    {
        $token = Str::random(64);

        Session::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'last_activity_at' => now(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        return $token;
    }
}
