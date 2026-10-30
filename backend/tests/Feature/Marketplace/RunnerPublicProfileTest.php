<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\IdentityVerification;
use App\Models\RunnerPublicProfile;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
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
            ->assertJsonPath(
                'data.profile.display_name',
                'Runner Profile Test'
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
                'Runner Profile Test'
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

    public function test_partial_profile_update_preserves_omitted_fields(): void
    {
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $token = $this->createSessionToken($runner);

        RunnerPublicProfile::query()->create([
            'profile_id' => (string) Str::uuid(),
            'account_id' => $runner->id,
            'public_slug' => 'partial-runner',
            'display_name' => 'Original Name',
            'bio' => 'Original bio',
            'service_area' => 'Tuaran',
            'services' => ['Parcel delivery'],
            'is_published' => true,
        ]);

        $this->withToken($token)
            ->postJson('/api/me/runner-profile', [
                'public_slug' => 'partial-runner',
            ])
            ->assertOk()
            ->assertJsonPath(
                'data.profile.display_name',
                'Runner Profile Test'
            )
            ->assertJsonPath('data.profile.bio', 'Original bio')
            ->assertJsonPath('data.profile.service_area', 'Tuaran')
            ->assertJsonPath(
                'data.profile.services.0',
                'Parcel delivery'
            )
            ->assertJsonPath('data.profile.is_published', true);

        $this->assertDatabaseHas('runner_public_profiles', [
            'account_id' => $runner->id,
            'display_name' => 'Original Name',
            'bio' => 'Original bio',
            'service_area' => 'Tuaran',
            'is_published' => true,
        ]);
    }

    public function test_partial_profile_update_changes_provided_values_and_preserves_omitted_fields(): void
    {
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $token = $this->createSessionToken($runner);

        RunnerPublicProfile::query()->create([
            'profile_id' => (string) Str::uuid(),
            'account_id' => $runner->id,
            'public_slug' => 'update-values-runner',
            'display_name' => 'Original Name',
            'bio' => 'Original bio',
            'service_area' => 'Tuaran',
            'services' => ['Parcel delivery'],
            'is_published' => true,
        ]);

        $this->withToken($token)
            ->postJson('/api/me/runner-profile', [
                'public_slug' => 'update-values-runner',
                'display_name' => 'Updated Name',
                'bio' => 'Updated bio',
            ])
            ->assertOk()
            ->assertJsonPath(
                'data.profile.display_name',
                'Runner Profile Test'
            )
            ->assertJsonPath('data.profile.bio', 'Updated bio')
            ->assertJsonPath('data.profile.service_area', 'Tuaran')
            ->assertJsonPath(
                'data.profile.services.0',
                'Parcel delivery'
            )
            ->assertJsonPath('data.profile.is_published', true);

        $this->assertDatabaseHas('runner_public_profiles', [
            'account_id' => $runner->id,
            'display_name' => 'Original Name',
            'bio' => 'Updated bio',
            'service_area' => 'Tuaran',
            'is_published' => true,
        ]);
    }

    public function test_partial_profile_update_can_explicitly_clear_nullable_fields(): void
    {
        $runner = $this->makeAccount(
            Account::RUNNER_STATUS_APPROVED
        );

        $token = $this->createSessionToken($runner);

        RunnerPublicProfile::query()->create([
            'profile_id' => (string) Str::uuid(),
            'account_id' => $runner->id,
            'public_slug' => 'clear-runner',
            'display_name' => 'Runner Name',
            'bio' => 'Remove this bio',
            'service_area' => 'Remove this area',
            'services' => ['Parcel delivery'],
            'is_published' => true,
        ]);

        $this->withToken($token)
            ->postJson('/api/me/runner-profile', [
                'public_slug' => 'clear-runner',
                'bio' => null,
                'service_area' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.profile.bio', null)
            ->assertJsonPath('data.profile.service_area', null)
            ->assertJsonPath(
                'data.profile.services.0',
                'Parcel delivery'
            )
            ->assertJsonPath('data.profile.is_published', true);

        $this->assertDatabaseHas('runner_public_profiles', [
            'account_id' => $runner->id,
            'bio' => null,
            'service_area' => null,
        ]);
    }

    public function test_verified_real_name_is_not_returned_in_public_profile(): void
    {
        $runner = $this->createApprovedRunner();

        $this->createIdentityVerification(
            $runner,
            IdentityVerification::STATUS_VERIFIED
        );

        $this->createPublicProfile($runner);

        $this->getJson('/api/runners/verified-runner')
            ->assertOk()
            ->assertJsonPath(
                'data.profile.display_name',
                'Nama Akaun Runner'
            )
            ->assertJsonMissingPath('data.profile.real_name')
            ->assertJsonMissing([
                'real_name' => 'Nama Sebenar Disahkan',
            ])
            ->assertJsonMissingPath('data.profile.account_id')
            ->assertJsonMissingPath('data.profile.phone')
            ->assertJsonMissingPath('data.profile.email')
            ->assertJsonMissingPath(
                'data.profile.mykad_number_encrypted'
            );
    }

    public function test_real_name_is_not_returned_when_identity_is_pending(): void
    {
        $runner = $this->createApprovedRunner();

        $this->createIdentityVerification(
            $runner,
            IdentityVerification::STATUS_PENDING
        );

        $this->createPublicProfile($runner);

        $this->getJson('/api/runners/verified-runner')
            ->assertOk()
            ->assertJsonPath(
                'data.profile.display_name',
                'Nama Akaun Runner'
            )
            ->assertJsonMissingPath('data.profile.real_name')
            ->assertJsonMissing([
                'real_name' => 'Nama Sebenar Disahkan',
            ]);
    }

    public function test_real_name_is_not_returned_when_identity_is_rejected(): void
    {
        $runner = $this->createApprovedRunner();

        $this->createIdentityVerification(
            $runner,
            IdentityVerification::STATUS_REJECTED
        );

        $this->createPublicProfile($runner);

        $this->getJson('/api/runners/verified-runner')
            ->assertOk()
            ->assertJsonPath(
                'data.profile.display_name',
                'Nama Akaun Runner'
            )
            ->assertJsonMissingPath('data.profile.real_name')
            ->assertJsonMissing([
                'real_name' => 'Nama Sebenar Disahkan',
            ]);
    }

    public function test_real_name_is_not_returned_without_identity_record(): void
    {
        $runner = $this->createApprovedRunner();

        $this->createPublicProfile($runner);

        $this->getJson('/api/runners/verified-runner')
            ->assertOk()
            ->assertJsonPath(
                'data.profile.display_name',
                'Nama Akaun Runner'
            )
            ->assertJsonMissingPath('data.profile.real_name');
    }

    public function test_public_display_name_uses_current_account_name_not_legacy_profile_name(): void
    {
        $runner = $this->createApprovedRunner();

        $this->createPublicProfile($runner);

        $runner->name = 'Nama Akaun Dikemas Kini';
        $runner->save();

        $this->getJson('/api/runners/verified-runner')
            ->assertOk()
            ->assertJsonPath(
                'data.profile.display_name',
                'Nama Akaun Dikemas Kini'
            )
            ->assertJsonMissingPath('data.profile.real_name');
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

    private function createApprovedRunner(): Account
    {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => 'Nama Akaun Runner',
            'password' => bcrypt('TestPassword123'),
            'role' => 'USER',
            'status' => 'ACTIVE',
            'runner_status' => Account::RUNNER_STATUS_APPROVED,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return Account::query()->findOrFail($id);
    }

    private function createIdentityVerification(
        Account $account,
        string $status
    ): IdentityVerification {
        return IdentityVerification::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'real_name' => 'Nama Sebenar Disahkan',
            'mykad_number_encrypted' => Crypt::encryptString(
                '900101145678'
            ),
            'status' => $status,
            'submitted_at' => now(),
            'reviewed_at' => in_array(
                $status,
                [
                    IdentityVerification::STATUS_VERIFIED,
                    IdentityVerification::STATUS_REJECTED,
                ],
                true
            ) ? now() : null,
        ]);
    }

    private function createPublicProfile(Account $account): void
    {
        RunnerPublicProfile::query()->create([
            'profile_id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'public_slug' => 'verified-runner',
            'display_name' => 'Nama Paparan Runner',
            'bio' => 'Profil ujian.',
            'service_area' => 'Tuaran',
            'services' => ['Penghantaran'],
            'is_published' => true,
            'views_count' => 0,
        ]);
    }
}
