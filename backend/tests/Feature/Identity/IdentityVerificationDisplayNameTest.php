<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\IdentityVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentityVerificationDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $name,
        string $role = 'USER'
    ): Account {
        return Account::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function authenticate(
        Account $account,
        string $token
    ): static {
        Session::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return $this->withHeader(
            'Authorization',
            'Bearer '.$token
        );
    }

    private function createPendingVerification(
        Account $account,
        string $realName
    ): IdentityVerification {
        return IdentityVerification::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'real_name' => $realName,
            'mykad_number_encrypted' => Crypt::encryptString(
                '900101145678'
            ),
            'status' => IdentityVerification::STATUS_PENDING,
            'submitted_at' => now(),
        ]);
    }

    public function test_approving_identity_does_not_change_display_name(): void
    {
        $root = $this->createAccount(
            'Nama Root',
            'ROOT_SUPER_ADMIN'
        );

        $applicant = $this->createAccount(
            'Nama Paparan Asal'
        );

        $verification = $this->createPendingVerification(
            $applicant,
            'Nama Sebenar Disahkan'
        );

        $this->authenticate(
            $root,
            'identity-display-name-root-token'
        )->postJson(
            '/api/admin/identity-verifications/'
                .$applicant->id
                .'/decision',
            [
                'decision' => IdentityVerification::STATUS_VERIFIED,
            ]
        )->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.identity_verification.status',
                IdentityVerification::STATUS_VERIFIED
            );

        $this->assertDatabaseHas('accounts', [
            'id' => $applicant->id,
            'name' => 'Nama Paparan Asal',
        ]);

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $verification->id,
            'account_id' => $applicant->id,
            'real_name' => 'Nama Sebenar Disahkan',
            'status' => IdentityVerification::STATUS_VERIFIED,
            'reviewer_account_id' => $root->id,
        ]);
    }

    public function test_root_can_review_own_identity_without_changing_display_name(): void
    {
        $root = $this->createAccount(
            'Nama Paparan Root',
            'ROOT_SUPER_ADMIN'
        );

        $verification = $this->createPendingVerification(
            $root,
            'Nama Sebenar Root'
        );

        $this->authenticate(
            $root,
            'identity-display-name-root-self-token'
        )->postJson(
            '/api/admin/identity-verifications/'
                .$root->id
                .'/decision',
            [
                'decision' => IdentityVerification::STATUS_VERIFIED,
            ]
        )->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.identity_verification.status',
                IdentityVerification::STATUS_VERIFIED
            );

        $this->assertDatabaseHas('accounts', [
            'id' => $root->id,
            'name' => 'Nama Paparan Root',
        ]);

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $verification->id,
            'account_id' => $root->id,
            'real_name' => 'Nama Sebenar Root',
            'status' => IdentityVerification::STATUS_VERIFIED,
            'reviewer_account_id' => $root->id,
        ]);
    }

    public function test_non_root_super_admin_cannot_review_own_identity(): void
    {
        $admin = $this->createAccount(
            'Nama Paparan Admin',
            'SUPER_ADMIN'
        );

        $verification = $this->createPendingVerification(
            $admin,
            'Nama Sebenar Admin'
        );

        $this->authenticate(
            $admin,
            'identity-display-name-admin-self-token'
        )->postJson(
            '/api/admin/identity-verifications/'
                .$admin->id
                .'/decision',
            [
                'decision' => IdentityVerification::STATUS_VERIFIED,
            ]
        )->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('accounts', [
            'id' => $admin->id,
            'name' => 'Nama Paparan Admin',
        ]);

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $verification->id,
            'account_id' => $admin->id,
            'status' => IdentityVerification::STATUS_PENDING,
        ]);
    }
}
