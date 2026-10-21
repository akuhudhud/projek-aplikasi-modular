<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\IdentityVerification;
use App\Models\IdentityVerificationDocument;
use App\Services\AuditLogService;
use App\Services\Identity\IdentityVerificationSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class IdentityVerificationSubmissionAuditIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_rolls_back_when_audit_recording_fails(): void
    {
        Storage::fake('local');

        $account = Account::create([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Identity Audit Test User',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $this->mock(AuditLogService::class, function ($mock): void {
            $mock->shouldReceive('record')
                ->once()
                ->withArgs(function (
                    Account $actor,
                    Account $target,
                    string $action,
                    string $reason
                ) use ($account): bool {
                    return $actor->is($account)
                        && $target->is($account)
                        && $action === 'IDENTITY_VERIFICATION_SUBMITTED'
                        && $reason ===
                            'Identity verification application submitted or resubmitted.';
                })
                ->andThrow(
                    new RuntimeException(
                        'Simulated audit recording failure.'
                    )
                );
        });

        try {
            app(IdentityVerificationSubmissionService::class)->submit(
                $account,
                'Real Name',
                '900101121234',
                [
                    UploadedFile::fake()->image('identity-document.jpg'),
                ]
            );

            $this->fail(
                'The submission should fail when audit recording fails.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Simulated audit recording failure.',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            0,
            IdentityVerification::query()
                ->where('account_id', $account->id)
                ->count()
        );

        $this->assertSame(
            0,
            IdentityVerificationDocument::query()->count()
        );

        $this->assertSame(0, AuditLog::query()->count());

        $this->assertSame(
            [],
            Storage::disk('local')->allFiles('identity-documents')
        );
    }
}
