<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\IdentityVerification;
use App\Models\IdentityVerificationDocument;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentityVerificationApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $role = 'USER',
        string $status = 'ACTIVE'
    ): Account {
        return Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Identity API Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => $status,
        ]);
    }

    private function grantReviewPermission(Account $account): void
    {
        AccountPermission::create([
            'account_id' => $account->id,
            'permission_key' => 'identity_verifications.review',
        ]);
    }

    private function authenticate(Account $account, string $token): void
    {
        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);
    }

    private function asAccount(Account $account, string $token): static
    {
        $this->authenticate($account, $token);

        return $this->withHeader(
            'Authorization',
            'Bearer '.$token
        );
    }

    private function createPendingVerification(
        Account $account,
        string $mykad = '900101145678'
    ): IdentityVerification {
        Storage::fake('local');

        $verification = IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'real_name' => 'Contoh Nama Sebenar',
            'mykad_number' => $mykad,
            'status' => IdentityVerification::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        $path = 'identity-documents/'.Str::uuid().'.pdf';

        Storage::disk('local')->put($path, '%PDF-test-content');

        IdentityVerificationDocument::create([
            'id' => (string) Str::uuid(),
            'identity_verification_id' => $verification->id,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen('%PDF-test-content'),
        ]);

        return $verification->fresh(['documents']);
    }

    public function test_status_endpoint_reports_not_submitted_without_exposing_identity_data(): void
    {
        $account = $this->createAccount();

        $this->asAccount($account, 'identity-status-token')
            ->getJson('/api/me/identity-verification')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.identity_verification.status',
                IdentityVerification::STATUS_NOT_SUBMITTED
            )
            ->assertJsonPath(
                'data.identity_verification.rejection_reason',
                null
            )
            ->assertJsonMissingPath(
                'data.identity_verification.real_name'
            )
            ->assertJsonMissingPath(
                'data.identity_verification.mykad_number'
            );
    }

    public function test_identity_submission_stores_plaintext_mykad_and_returns_only_status(): void
    {
        Storage::fake('local');

        $account = $this->createAccount();

        $response = $this->asAccount(
            $account,
            'identity-submit-token'
        )->post('/api/me/identity-verification', [
            'real_name' => 'Nama Sebenar Pengguna',
            'mykad_number' => '900101145678',
            'documents' => [
                UploadedFile::fake()->image('mykad.png'),
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.identity_verification.status',
                IdentityVerification::STATUS_PENDING
            )
            ->assertJsonMissing([
                'mykad_number' => '900101145678',
                'real_name' => 'Nama Sebenar Pengguna',
            ]);

        $verification = IdentityVerification::query()
            ->where('account_id', $account->id)
            ->firstOrFail();

        $this->assertSame(
            '900101145678',
            $verification->mykad_number
        );

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $verification->id,
            'account_id' => $account->id,
            'mykad_number' => '900101145678',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $account->id,
            'target_account_id' => $account->id,
            'action' => 'IDENTITY_VERIFICATION_SUBMITTED',
            'reason' => 'Identity verification application submitted or resubmitted.',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'reason' => '900101145678',
        ]);
    }

    public function test_super_admin_without_review_permission_cannot_view_identity_application(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $applicant = $this->createAccount();

        $this->createPendingVerification($applicant);

        $this->asAccount($admin, 'identity-no-permission-token')
            ->getJson(
                '/api/admin/identity-verifications/'.$applicant->id
            )
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_super_admin_with_permission_sees_masked_mykad_and_no_storage_path(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);

        $applicant = $this->createAccount();
        $verification = $this->createPendingVerification($applicant);

        $response = $this->asAccount(
            $admin,
            'identity-review-view-token'
        )->getJson(
            '/api/admin/identity-verifications/'.$applicant->id
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.identity_verification.mykad_number_masked',
                '********5678'
            )
            ->assertJsonPath(
                'data.identity_verification.documents.0.id',
                $verification->documents->first()->id
            )
            ->assertJsonMissingPath(
                'data.identity_verification.mykad_number'
            )
            ->assertJsonMissingPath(
                'data.identity_verification.documents.0.path'
            )
            ->assertJsonMissingPath(
                'data.identity_verification.documents.0.disk'
            );

        $response->assertDontSee('900101145678');
    }

    public function test_super_admin_with_permission_can_reject_and_audit_does_not_store_free_text_reason(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);

        $applicant = $this->createAccount();
        $this->createPendingVerification($applicant);

        $this->asAccount(
            $admin,
            'identity-reject-token'
        )->postJson(
            '/api/admin/identity-verifications/'.$applicant->id.'/decision',
            [
                'decision' => 'REJECTED',
                'rejection_reason' => 'Dokumen tidak jelas.',
            ]
        )->assertOk()
            ->assertJsonPath(
                'data.identity_verification.status',
                IdentityVerification::STATUS_REJECTED
            );

        $this->assertDatabaseHas('identity_verifications', [
            'account_id' => $applicant->id,
            'status' => IdentityVerification::STATUS_REJECTED,
            'rejection_reason' => 'Dokumen tidak jelas.',
            'reviewer_account_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $applicant->id,
            'action' => 'IDENTITY_VERIFICATION_REJECTED',
            'reason' => 'Identity verification marked REJECTED.',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'reason' => 'Dokumen tidak jelas.',
        ]);
    }

    public function test_super_admin_cannot_review_own_identity_verification(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);

        $verification = $this->createPendingVerification($admin);

        $this->asAccount(
            $admin,
            'identity-admin-self-review-token'
        )->postJson(
            '/api/admin/identity-verifications/'.$admin->id.'/decision',
            ['decision' => 'VERIFIED']
        )->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'message',
                'Administrators cannot review their own identity verification.'
            );

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $verification->id,
            'account_id' => $admin->id,
            'status' => IdentityVerification::STATUS_PENDING,
            'reviewer_account_id' => null,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $admin->id,
            'action' => 'IDENTITY_VERIFICATION_VERIFIED',
        ]);
    }

    public function test_root_can_review_own_identity_verification(): void
    {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');
        $this->createPendingVerification($root);

        $this->asAccount(
            $root,
            'identity-root-self-review-token'
        )->postJson(
            '/api/admin/identity-verifications/'.$root->id.'/decision',
            ['decision' => 'VERIFIED']
        )->assertOk()
            ->assertJsonPath(
                'data.identity_verification.status',
                IdentityVerification::STATUS_VERIFIED
            );

        $this->assertDatabaseHas('identity_verifications', [
            'account_id' => $root->id,
            'status' => IdentityVerification::STATUS_VERIFIED,
            'reviewer_account_id' => $root->id,
        ]);
    }

    public function test_rejected_decision_requires_a_non_empty_reason(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);

        $applicant = $this->createAccount();
        $this->createPendingVerification($applicant);

        $this->asAccount(
            $admin,
            'identity-empty-reason-token'
        )->postJson(
            '/api/admin/identity-verifications/'.$applicant->id.'/decision',
            [
                'decision' => 'REJECTED',
                'rejection_reason' => '   ',
            ]
        )->assertUnprocessable();

        $this->assertDatabaseHas('identity_verifications', [
            'account_id' => $applicant->id,
            'status' => IdentityVerification::STATUS_PENDING,
        ]);
    }

    public function test_only_pending_identity_verifications_can_be_reviewed(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);

        $applicant = $this->createAccount();
        $verification = $this->createPendingVerification($applicant);

        $verification->status = IdentityVerification::STATUS_VERIFIED;
        $verification->save();

        $this->asAccount(
            $admin,
            'identity-repeat-decision-token'
        )->postJson(
            '/api/admin/identity-verifications/'.$applicant->id.'/decision',
            ['decision' => 'REJECTED', 'rejection_reason' => 'Repeat decision']
        )->assertUnprocessable();

        $this->assertDatabaseHas('identity_verifications', [
            'account_id' => $applicant->id,
            'status' => IdentityVerification::STATUS_VERIFIED,
        ]);
    }

    public function test_reviewer_can_download_document_through_protected_endpoint(): void
    {
        Storage::fake('local');

        $admin = $this->createAccount('SUPER_ADMIN');
        $this->grantReviewPermission($admin);

        $applicant = $this->createAccount();
        $verification = $this->createPendingVerification($applicant);
        $document = $verification->documents->first();

        $response = $this->asAccount(
            $admin,
            'identity-document-download-token'
        )->get(
            '/api/admin/identity-verifications/'.$applicant->id
                .'/documents/'.$document->id
        );

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $cacheControl = array_map(
            'trim',
            explode(',', $response->headers->get('Cache-Control', ''))
        );

        $this->assertContains('private', $cacheControl);
        $this->assertContains('no-store', $cacheControl);
    }
}
