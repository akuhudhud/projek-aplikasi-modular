<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\IdentityVerification;
use App\Models\IdentityVerificationDocument;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentityVerificationDeletionApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $role = 'USER',
        string $status = 'ACTIVE'
    ): Account {
        return Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Identity Deletion Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => $status,
        ]);
    }

    private function grantDeletionPermission(Account $account): void
    {
        AccountPermission::create([
            'account_id' => $account->id,
            'permission_key' => 'identity_verifications.delete_data',
        ]);
    }

    private function asAccount(
        Account $account,
        string $token
    ): static {
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

    private function createVerificationWithDocument(
        Account $account
    ): array {
        Storage::fake('local');

        $verification = IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'real_name' => 'Nama Ujian',
            'mykad_number_encrypted' => Crypt::encryptString(
                '900101145678'
            ),
            'status' => IdentityVerification::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $path = 'identity-documents/'.Str::uuid().'.pdf';
        $contents = '%PDF-test-content';

        Storage::disk('local')->put($path, $contents);

        $document = IdentityVerificationDocument::create([
            'id' => (string) Str::uuid(),
            'identity_verification_id' => $verification->id,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($contents),
        ]);

        return [
            'verification' => $verification,
            'document' => $document,
            'path' => $path,
        ];
    }

    public function test_super_admin_without_deletion_permission_is_forbidden(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $applicant = $this->createAccount();

        $records = $this->createVerificationWithDocument($applicant);

        $this->asAccount(
            $admin,
            'identity-delete-denied-token'
        )->deleteJson(
            '/api/admin/identity-verifications/'.$applicant->id.'/data',
            ['reason' => 'Permintaan pemadaman']
        )->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $records['verification']->id,
            'account_id' => $applicant->id,
        ]);

        Storage::disk('local')->assertExists($records['path']);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $applicant->id,
            'action' => 'IDENTITY_VERIFICATION_DATA_DELETED',
        ]);
    }

    public function test_authorized_super_admin_can_delete_identity_data_and_document(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantDeletionPermission($admin);

        $applicant = $this->createAccount();
        $records = $this->createVerificationWithDocument($applicant);

        $this->asAccount(
            $admin,
            'identity-delete-authorized-token'
        )->deleteJson(
            '/api/admin/identity-verifications/'.$applicant->id.'/data',
            ['reason' => 'Permintaan pemadaman sah']
        )->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'message',
                'Data pengesahan identiti berjaya dipadam.'
            );

        $this->assertDatabaseMissing('identity_verifications', [
            'id' => $records['verification']->id,
        ]);

        $this->assertDatabaseMissing('identity_verification_documents', [
            'id' => $records['document']->id,
        ]);

        Storage::disk('local')->assertMissing($records['path']);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $applicant->id,
            'action' => 'IDENTITY_VERIFICATION_DATA_DELETED',
        ]);

        $audit = \App\Models\AuditLog::query()
            ->where('actor_account_id', $admin->id)
            ->where('target_account_id', $applicant->id)
            ->where('action', 'IDENTITY_VERIFICATION_DATA_DELETED')
            ->firstOrFail();

        $this->assertStringNotContainsString(
            '900101145678',
            $audit->reason
        );

        $this->assertStringNotContainsString(
            $records['path'],
            $audit->reason
        );
    }

    public function test_root_can_delete_identity_data_without_assigned_permission(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');
        $applicant = $this->createAccount();

        $records = $this->createVerificationWithDocument($applicant);

        $this->asAccount(
            $root,
            'identity-delete-root-token'
        )->deleteJson(
            '/api/admin/identity-verifications/'.$applicant->id.'/data',
            ['reason' => 'Pemadaman oleh Root']
        )->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('identity_verifications', [
            'id' => $records['verification']->id,
        ]);

        Storage::disk('local')->assertMissing($records['path']);
    }

    public function test_deletion_requires_a_reason(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');
        $applicant = $this->createAccount();

        $records = $this->createVerificationWithDocument($applicant);

        $this->asAccount(
            $root,
            'identity-delete-no-reason-token'
        )->deleteJson(
            '/api/admin/identity-verifications/'.$applicant->id.'/data',
            ['reason' => '']
        )->assertUnprocessable();

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $records['verification']->id,
        ]);

        Storage::disk('local')->assertExists($records['path']);
    }

    public function test_deletion_rejects_a_reason_shorter_than_five_characters(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');
        $applicant = $this->createAccount();

        $records = $this->createVerificationWithDocument($applicant);

        $this->asAccount(
            $root,
            'identity-delete-short-reason-token'
        )->deleteJson(
            '/api/admin/identity-verifications/'.$applicant->id.'/data',
            ['reason' => 'No']
        )->assertUnprocessable();

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $records['verification']->id,
        ]);

        Storage::disk('local')->assertExists($records['path']);
    }

    public function test_deleting_a_missing_verification_returns_not_found(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');
        $applicant = $this->createAccount();

        $this->asAccount(
            $root,
            'identity-delete-missing-token'
        )->deleteJson(
            '/api/admin/identity-verifications/'.$applicant->id.'/data',
            ['reason' => 'Rekod tidak diperlukan']
        )->assertNotFound();
    }
}
