<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\IdentityVerification;
use App\Models\IdentityVerificationDocument;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentityVerificationTargetAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(string $role = 'USER'): Account
    {
        return Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Identity Target Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function grantPermission(
        Account $account,
        string $permission
    ): void {
        AccountPermission::create([
            'account_id' => $account->id,
            'permission_key' => $permission,
        ]);
    }

    private function authenticate(Account $account): static
    {
        $token = 'identity-target-'.Str::random(32);

        Session::create([
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

    private function createVerification(
        Account $account
    ): IdentityVerification {
        return IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'verified_name' => 'Nama Ujian',
            'mykad_number' => '900101145678',
            'status' => IdentityVerification::STATUS_PENDING,
            'submitted_at' => now(),
        ]);
    }

    private function createDocument(
        IdentityVerification $verification
    ): IdentityVerificationDocument {
        Storage::fake('local');

        $path = 'identity-documents/'.Str::uuid().'.pdf';

        Storage::disk('local')->put($path, '%PDF-identity-target-test');

        return IdentityVerificationDocument::create([
            'id' => (string) Str::uuid(),
            'identity_verification_id' => $verification->id,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen('%PDF-identity-target-test'),
        ]);
    }

    public function test_super_admin_queue_only_contains_user_records(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $user = $this->createAccount('USER');
        $anotherAdmin = $this->createAccount('SUPER_ADMIN');
        $root = $this->createAccount('ROOT_SUPER_ADMIN');

        $this->grantPermission(
            $admin,
            'identity_verifications.review'
        );

        $this->createVerification($user);
        $this->createVerification($anotherAdmin);
        $this->createVerification($root);

        $response = $this->authenticate($admin)
            ->getJson('/api/admin/identity-verifications?status=ALL');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $accountIds = collect(
            $response->json('data.verifications')
        )->pluck('account_id')->all();

        $this->assertContains($user->id, $accountIds);
        $this->assertNotContains($anotherAdmin->id, $accountIds);
        $this->assertNotContains($root->id, $accountIds);
    }

    public function test_super_admin_cannot_open_another_super_admin_identity_record(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $target = $this->createAccount('SUPER_ADMIN');

        $this->grantPermission(
            $admin,
            'identity_verifications.review'
        );

        $this->createVerification($target);

        $this->authenticate($admin)
            ->getJson(
                '/api/admin/identity-verifications/'.$target->id
            )
            ->assertForbidden();

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $target->id,
            'action' => 'IDENTITY_VERIFICATION_VIEWED',
        ]);
    }

    public function test_super_admin_cannot_decide_another_super_admin_identity_record(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $target = $this->createAccount('SUPER_ADMIN');

        $this->grantPermission(
            $admin,
            'identity_verifications.review'
        );

        $verification = $this->createVerification($target);

        $this->authenticate($admin)
            ->postJson(
                '/api/admin/identity-verifications/'.$target->id.'/decision',
                [
                    'decision' => 'VERIFIED',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $verification->id,
            'status' => IdentityVerification::STATUS_PENDING,
            'reviewer_account_id' => null,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $target->id,
            'action' => 'IDENTITY_VERIFICATION_VERIFIED',
        ]);
    }

    public function test_super_admin_cannot_view_another_super_admin_document(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $target = $this->createAccount('SUPER_ADMIN');

        $this->grantPermission(
            $admin,
            'identity_verifications.review'
        );

        $verification = $this->createVerification($target);
        $document = $this->createDocument($verification);

        $this->authenticate($admin)
            ->get(
                '/api/admin/identity-verifications/'.$target->id
                .'/documents/'.$document->id
            )
            ->assertForbidden();

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $target->id,
            'action' => 'IDENTITY_VERIFICATION_DOCUMENT_VIEWED',
        ]);
    }

    public function test_super_admin_cannot_delete_another_super_admin_identity_data(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $target = $this->createAccount('SUPER_ADMIN');

        $this->grantPermission(
            $admin,
            'identity_verifications.delete_data'
        );

        $verification = $this->createVerification($target);
        $document = $this->createDocument($verification);

        $this->authenticate($admin)
            ->deleteJson(
                '/api/admin/identity-verifications/'.$target->id.'/data',
                [
                    'reason' => 'Ujian sekatan pemadaman',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $verification->id,
            'account_id' => $target->id,
        ]);

        $this->assertDatabaseHas('identity_verification_documents', [
            'id' => $document->id,
            'identity_verification_id' => $verification->id,
        ]);

        Storage::disk('local')->assertExists($document->path);
    }

    public function test_super_admin_cannot_delete_own_identity_data(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $this->grantPermission(
            $admin,
            'identity_verifications.delete_data'
        );

        $verification = $this->createVerification($admin);

        $this->authenticate($admin)
            ->deleteJson(
                '/api/admin/identity-verifications/'.$admin->id.'/data',
                [
                    'reason' => 'Ujian sekatan akaun sendiri',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $verification->id,
            'account_id' => $admin->id,
        ]);
    }

    public function test_root_can_view_all_identity_verification_records(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');
        $user = $this->createAccount('USER');
        $admin = $this->createAccount('SUPER_ADMIN');
        $anotherRoot = $this->createAccount('ROOT_SUPER_ADMIN');

        $this->createVerification($user);
        $this->createVerification($admin);
        $this->createVerification($anotherRoot);

        $response = $this->authenticate($root)
            ->getJson('/api/admin/identity-verifications?status=ALL');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $accountIds = collect(
            $response->json('data.verifications')
        )->pluck('account_id')->all();

        $this->assertContains($user->id, $accountIds);
        $this->assertContains($admin->id, $accountIds);
        $this->assertContains($anotherRoot->id, $accountIds);
    }
}
