<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\IdentityVerification;
use App\Models\RunnerLicenseVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerLicenseInitialApplicationDecisionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_separate_license_decisions_cannot_mutate_pending_runner_application(): void
    {
        Storage::fake('local');

        $root = $this->createAccount(
            'Root Owner',
            'ROOT_SUPER_ADMIN'
        );

        $applicant = $this->createAccount(
            'Runner Applicant',
            'USER'
        );

        $applicant->runner_status = Account::RUNNER_STATUS_PENDING;
        $applicant->save();

        IdentityVerification::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $applicant->id,
            'real_name' => 'Nama Pemohon',
            'mykad_number_encrypted' => Crypt::encryptString(
                '900101145678'
            ),
            'status' => IdentityVerification::STATUS_VERIFIED,
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'verified_at' => now(),
        ]);

        $path = 'runner-license-documents/'
            .Str::uuid()
            .'.pdf';

        Storage::disk('local')->put(
            $path,
            '%PDF-initial-runner-license'
        );

        $verification = RunnerLicenseVerification::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $applicant->id,
            'license_number_encrypted' => Crypt::encryptString(
                'D123456789012'
            ),
            'license_type' => 'GENERAL',
            'license_class' => 'D',
            'previous_license_verification_id' => null,
            'expires_at' => now()->addYear()->toDateString(),
            'status' => RunnerLicenseVerification::STATUS_PENDING,
            'is_current' => false,
            'submitted_at' => now(),
            'reviewed_at' => null,
            'reviewer_account_id' => null,
            'rejection_reason' => null,
            'disk' => 'local',
            'document_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen('%PDF-initial-runner-license'),
        ]);

        $token = 'initial-runner-decision-guard-token';

        Session::query()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $root->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        foreach ([
            [
                'decision' => 'VERIFIED',
            ],
            [
                'decision' => 'REJECTED',
                'rejection_reason' => 'Dokumen tidak jelas.',
            ],
        ] as $decision) {
            $this->withHeader(
                'Authorization',
                'Bearer '.$token
            )->postJson(
                '/api/admin/runner-license-verifications/'
                    .$applicant->id.'/decision',
                $decision
            )->assertUnprocessable()
                ->assertJsonPath('success', false)
                ->assertJsonPath(
                    'message',
                    'Lesen bagi permohonan awal Runner mesti disemak melalui keputusan permohonan Runner.'
                );

            $this->assertDatabaseHas('runner_license_verifications', [
                'id' => $verification->id,
                'account_id' => $applicant->id,
                'status' => RunnerLicenseVerification::STATUS_PENDING,
                'is_current' => false,
                'reviewer_account_id' => null,
                'reviewed_at' => null,
            ]);

            $this->assertDatabaseHas('accounts', [
                'id' => $applicant->id,
                'runner_status' => Account::RUNNER_STATUS_PENDING,
            ]);

            $this->assertDatabaseMissing('audit_logs', [
                'actor_account_id' => $root->id,
                'target_account_id' => $applicant->id,
                'action' => 'RUNNER_LICENSE_VERIFICATION_VERIFIED',
            ]);

            $this->assertDatabaseMissing('audit_logs', [
                'actor_account_id' => $root->id,
                'target_account_id' => $applicant->id,
                'action' => 'RUNNER_LICENSE_VERIFICATION_REJECTED',
            ]);
        }

        Storage::disk('local')->assertExists($path);
    }

    private function createAccount(
        string $name,
        string $role
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
}
