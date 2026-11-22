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

class IdentityVerificationSelfReviewAccessTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperAdmin(): Account
    {
        $account = Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Self Review Admin',
            'phone' => null,
            'email' => 'self-review-admin@example.com',
            'password' => 'Password1',
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        AccountPermission::create([
            'account_id' => $account->id,
            'permission_key' => 'identity_verifications.review',
        ]);

        return $account;
    }

    private function authenticate(Account $account): static
    {
        $token = 'identity-self-review-'.Str::random(24);

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

    private function createOwnIdentityDocument(
        Account $account
    ): IdentityVerificationDocument {
        Storage::fake('local');

        $verification = IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'verified_name' => 'Self Review Admin',
            'mykad_number' => '900101145678',
            'status' => IdentityVerification::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $path = 'identity-documents/'.Str::uuid().'.pdf';

        Storage::disk('local')->put(
            $path,
            '%PDF-self-review-test'
        );

        return IdentityVerificationDocument::create([
            'id' => (string) Str::uuid(),
            'identity_verification_id' => $verification->id,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen('%PDF-self-review-test'),
        ]);
    }

    public function test_super_admin_cannot_open_own_identity_details(): void
    {
        $admin = $this->createSuperAdmin();

        $this->createOwnIdentityDocument($admin);

        $this->authenticate($admin)
            ->getJson(
                '/api/admin/identity-verifications/'.$admin->id
            )
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Administrators cannot review their own identity verification.'
            );

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $admin->id,
            'action' => 'IDENTITY_VERIFICATION_VIEWED',
        ]);
    }

    public function test_super_admin_cannot_download_own_identity_document(): void
    {
        $admin = $this->createSuperAdmin();

        $document = $this->createOwnIdentityDocument($admin);

        $this->authenticate($admin)
            ->getJson(
                '/api/admin/identity-verifications/'.$admin->id
                .'/documents/'.$document->id
            )
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Administrators cannot review their own identity verification.'
            );

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $admin->id,
            'action' => 'IDENTITY_VERIFICATION_DOCUMENT_DOWNLOADED',
        ]);
    }
}
