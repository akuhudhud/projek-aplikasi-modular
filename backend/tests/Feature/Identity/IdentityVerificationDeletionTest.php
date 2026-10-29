<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\IdentityVerification;
use App\Models\IdentityVerificationDocument;
use App\Models\Session;
use App\Services\AuditLogService;
use App\Services\Identity\IdentityVerificationDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class IdentityVerificationDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $role = 'USER',
        string $status = 'ACTIVE',
        string $name = 'Identity Deletion Test'
    ): Account {
        return Account::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
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

    private function authenticateAccount(
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

    private function createVerification(
        Account $account,
        string $status = IdentityVerification::STATUS_PENDING
    ): IdentityVerification {
        return IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'real_name' => 'Nama Ujian',
            'mykad_number_encrypted' => Crypt::encryptString(
                '900101145678'
            ),
            'status' => $status,
            'submitted_at' => now(),
            'verified_at' => $status === IdentityVerification::STATUS_VERIFIED
                ? now()
                : null,
        ]);
    }

    private function createVerificationWithDocuments(
        Account $applicant,
        array $contentsList = ['%PDF-test-content'],
        string $status = IdentityVerification::STATUS_PENDING
    ): array {
        $verification = $this->createVerification(
            $applicant,
            $status
        );

        $documents = [];

        foreach ($contentsList as $contents) {
            $path = 'identity-documents/'.Str::uuid().'.pdf';

            Storage::disk('local')->put($path, $contents);

            $record = IdentityVerificationDocument::create([
                'id' => (string) Str::uuid(),
                'identity_verification_id' => $verification->id,
                'disk' => 'local',
                'path' => $path,
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($contents),
            ]);

            $documents[] = [
                'record' => $record,
                'path' => $path,
                'contents' => $contents,
            ];
        }

        return [$verification, $documents];
    }

    private function createVerificationWithOneDocument(
        Account $applicant
    ): array {
        Storage::fake('local');

        [$verification, $documents] = $this->createVerificationWithDocuments(
            $applicant
        );

        return [
            'verification' => $verification,
            'document' => $documents[0]['record'],
            'path' => $documents[0]['path'],
        ];
    }

    private function assertVerificationAndDocumentsExist(
        IdentityVerification $verification,
        array $documents
    ): void {
        $this->assertDatabaseHas('identity_verifications', [
            'id' => $verification->id,
            'account_id' => $verification->account_id,
        ]);

        foreach ($documents as $document) {
            $this->assertDatabaseHas('identity_verification_documents', [
                'id' => $document['record']->id,
                'identity_verification_id' => $verification->id,
                'path' => $document['path'],
            ]);

            $this->assertTrue(
                Storage::disk('local')->exists($document['path']),
                'Fail sepatutnya wujud semula: '.$document['path']
            );

            $this->assertSame(
                $document['contents'],
                Storage::disk('local')->get($document['path'])
            );
        }
    }

    private function authenticateAsRoot(Account $root): void
    {
        $token = 'runner-protection-'.Str::uuid();

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $root->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        );
    }

    private function assertDeletionBlockedForRunnerStatus(
        string $runnerStatus
    ): void {
        $root = $this->createAccount('ROOT_SUPER_ADMIN');
        $runner = $this->createAccount();

        DB::table('accounts')
            ->where('id', $runner->id)
            ->update([
                'runner_status' => $runnerStatus,
            ]);

        $verification = $this->createVerification(
            $runner,
            IdentityVerification::STATUS_VERIFIED
        );

        $this->authenticateAsRoot($root);

        $this->deleteJson(
            '/api/admin/identity-verifications/'.$runner->id.'/data',
            [
                'reason' => 'Ujian perlindungan Runner',
            ]
        )->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $verification->id,
            'account_id' => $runner->id,
            'status' => IdentityVerification::STATUS_VERIFIED,
        ]);
    }

    public function test_super_admin_without_deletion_permission_is_forbidden(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');
        $applicant = $this->createAccount();

        $records = $this->createVerificationWithOneDocument($applicant);

        $this->authenticateAccount(
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
        $records = $this->createVerificationWithOneDocument($applicant);

        $this->authenticateAccount(
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

        $records = $this->createVerificationWithOneDocument($applicant);

        $this->authenticateAccount(
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

        $records = $this->createVerificationWithOneDocument($applicant);

        $this->authenticateAccount(
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

        $records = $this->createVerificationWithOneDocument($applicant);

        $this->authenticateAccount(
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

        $this->authenticateAccount(
            $root,
            'identity-delete-missing-token'
        )->deleteJson(
            '/api/admin/identity-verifications/'.$applicant->id.'/data',
            ['reason' => 'Rekod tidak diperlukan']
        )->assertNotFound();
    }

    public function test_file_is_restored_when_disk_throws_after_deleting_it(): void
    {
        Storage::fake('local');

        $actor = $this->createAccount('ROOT_SUPER_ADMIN');
        $applicant = $this->createAccount('USER');

        [$verification, $documents] = $this->createVerificationWithDocuments(
            $applicant,
            ['%PDF-delete-then-throw-regression']
        );

        $document = $documents[0];
        $realDisk = Storage::disk('local');
        $disk = Mockery::mock($realDisk)->makePartial();

        $disk->shouldReceive('delete')
            ->once()
            ->andReturnUsing(
                function (string $filePath) use ($realDisk): bool {
                    $realDisk->delete($filePath);

                    throw new RuntimeException(
                        'Simulated storage failure after deletion.'
                    );
                }
            );

        Storage::shouldReceive('disk')
            ->with('local')
            ->andReturn($disk);

        $auditLogService = Mockery::mock(AuditLogService::class);
        $auditLogService->shouldNotReceive('record');

        $service = new IdentityVerificationDeletionService(
            $auditLogService
        );

        try {
            $service->delete(
                $actor,
                $applicant->id,
                'Ujian pemulihan fail'
            );

            $this->fail(
                'Pemadaman sepatutnya gagal apabila storan melemparkan ralat.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Pemadaman fail gagal. Fail yang telah dipadam dipulihkan dan rekod pangkalan data dikekalkan.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('identity_verifications', [
            'id' => $verification->id,
            'account_id' => $applicant->id,
        ]);

        $this->assertDatabaseHas('identity_verification_documents', [
            'id' => $document['record']->id,
            'identity_verification_id' => $verification->id,
            'path' => $document['path'],
        ]);

        $this->assertTrue(
            $realDisk->exists($document['path']),
            'Fail yang dipadam sebelum ralat mesti dipulihkan.'
        );

        $this->assertSame(
            $document['contents'],
            $realDisk->get($document['path']),
            'Kandungan fail yang dipulihkan mesti sama dengan kandungan asal.'
        );
    }

    public function test_files_and_database_records_are_restored_when_database_transaction_fails(): void
    {
        Storage::fake('local');

        $actor = $this->createAccount('ROOT_SUPER_ADMIN');
        $applicant = $this->createAccount('USER');

        [$verification, $documents] = $this->createVerificationWithDocuments(
            $applicant,
            ['%PDF-rollback-test-content']
        );

        $auditLogService = Mockery::mock(AuditLogService::class);

        $auditLogService
            ->shouldReceive('record')
            ->once()
            ->andThrow(new RuntimeException('Simulated audit failure.'));

        $service = new IdentityVerificationDeletionService(
            $auditLogService
        );

        try {
            $service->delete(
                $actor,
                $applicant->id,
                'Ujian rollback transaksi'
            );

            $this->fail(
                'Pemadaman sepatutnya gagal apabila penulisan audit gagal.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Transaksi pangkalan data gagal. Fail dokumen telah dipulihkan.',
                $exception->getMessage()
            );
        }

        $this->assertVerificationAndDocumentsExist(
            $verification,
            $documents
        );
    }

    public function test_previous_files_are_restored_when_a_later_file_deletion_fails(): void
    {
        Storage::fake('local');

        $actor = $this->createAccount('ROOT_SUPER_ADMIN');
        $applicant = $this->createAccount('USER');

        [$verification, $documents] = $this->createVerificationWithDocuments(
            $applicant,
            [
                '%PDF-first-document',
                '%PDF-second-document',
            ]
        );

        $realDisk = Storage::disk('local');
        $deleteCalls = 0;
        $disk = Mockery::mock($realDisk)->makePartial();

        $disk->shouldReceive('delete')
            ->twice()
            ->andReturnUsing(
                function ($path) use ($realDisk, &$deleteCalls): bool {
                    $deleteCalls++;

                    if ($deleteCalls === 1) {
                        return $realDisk->delete($path);
                    }

                    return false;
                }
            );

        Storage::shouldReceive('disk')
            ->with('local')
            ->andReturn($disk);

        $auditLogService = Mockery::mock(AuditLogService::class);
        $auditLogService->shouldNotReceive('record');

        $service = new IdentityVerificationDeletionService(
            $auditLogService
        );

        try {
            $service->delete(
                $actor,
                $applicant->id,
                'Ujian kegagalan fail'
            );

            $this->fail(
                'Pemadaman sepatutnya gagal apabila satu fail gagal dipadam.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Pemadaman fail gagal. Fail yang telah dipadam dipulihkan dan rekod pangkalan data dikekalkan.',
                $exception->getMessage()
            );
        }

        $this->assertSame(2, $deleteCalls);

        $this->assertVerificationAndDocumentsExist(
            $verification,
            $documents
        );
    }

    public function test_identity_data_deletion_is_blocked_for_pending_runner(): void
    {
        $this->assertDeletionBlockedForRunnerStatus(
            Account::RUNNER_STATUS_PENDING
        );
    }

    public function test_identity_data_deletion_is_blocked_for_approved_runner(): void
    {
        $this->assertDeletionBlockedForRunnerStatus(
            Account::RUNNER_STATUS_APPROVED
        );
    }
}
