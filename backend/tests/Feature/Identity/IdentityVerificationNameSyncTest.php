<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\IdentityVerification;
use App\Models\IdentityVerificationDocument;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentityVerificationNameSyncTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(string $role = 'USER'): Account
    {
        return Account::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Nama Akaun Asal',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function authenticate(Account $account, string $token): void
    {
        Session::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);
    }

    private function createPendingVerification(
        Account $account
    ): IdentityVerification {
        Storage::fake('local');

        $verification = IdentityVerification::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'real_name' => 'Nama Penuh Disahkan',
            'mykad_number_encrypted' => Crypt::encryptString('900101145678'),
            'status' => IdentityVerification::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $path = 'identity-documents/'.Str::uuid().'.pdf';

        Storage::disk('local')->put($path, '%PDF-test-content');

        IdentityVerificationDocument::query()->create([
            'id' => (string) Str::uuid(),
            'identity_verification_id' => $verification->id,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen('%PDF-test-content'),
        ]);

        return $verification;
    }

    public function test_verified_identity_updates_account_name(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');
        $this->createPendingVerification($root);

        $token = 'identity-name-sync-token';

        $this->authenticate($root, $token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson(
                '/api/admin/identity-verifications/'.$root->id.'/decision',
                ['decision' => 'VERIFIED']
            )
            ->assertOk()
            ->assertJsonPath(
                'data.identity_verification.status',
                IdentityVerification::STATUS_VERIFIED
            );

        $this->assertDatabaseHas('accounts', [
            'id' => $root->id,
            'name' => 'Nama Penuh Disahkan',
        ]);

        $this->assertDatabaseHas('identity_verifications', [
            'account_id' => $root->id,
            'status' => IdentityVerification::STATUS_VERIFIED,
            'reviewer_account_id' => $root->id,
        ]);
    }

    public function test_rejected_identity_does_not_change_account_name(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');
        $this->createPendingVerification($root);

        $token = 'identity-name-rejection-token';

        $this->authenticate($root, $token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson(
                '/api/admin/identity-verifications/'.$root->id.'/decision',
                [
                    'decision' => 'REJECTED',
                    'rejection_reason' => 'Dokumen tidak jelas.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.identity_verification.status',
                IdentityVerification::STATUS_REJECTED
            );

        $this->assertDatabaseHas('accounts', [
            'id' => $root->id,
            'name' => 'Nama Akaun Asal',
        ]);

        $this->assertDatabaseHas('identity_verifications', [
            'account_id' => $root->id,
            'status' => IdentityVerification::STATUS_REJECTED,
            'rejection_reason' => 'Dokumen tidak jelas.',
        ]);
    }
}
