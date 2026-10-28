<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\IdentityVerification;
use App\Models\RunnerPublicProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerPublicProfileRealNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_real_name_is_returned_without_replacing_display_name(): void
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
            ->assertJsonPath(
                'data.profile.real_name',
                'Nama Sebenar Disahkan'
            )
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
            );
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
