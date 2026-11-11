<?php

namespace Tests\Feature\Core;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\DrivingLicenseCatalog;
use App\Models\DrivingLicenseType;
use App\Models\IdentityVerification;
use App\Models\RunnerLicenseVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerApplicationResubmissionTest extends TestCase
{
    use RefreshDatabase;

    private DrivingLicenseType $licenseType;

    private DrivingLicenseCatalog $licenseClass;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->licenseType = DrivingLicenseType::create([
            'id' => (string) Str::uuid(),
            'code' => 'GENERAL',
            'name' => 'Lesen Am',
            'description' => 'Jenis lesen ujian.',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->licenseClass = DrivingLicenseCatalog::create([
            'id' => (string) Str::uuid(),
            'driving_license_type_id' => $this->licenseType->id,
            'code' => 'D',
            'name' => 'Kelas D',
            'description' => 'Kelas lesen ujian.',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function createAccount(
        string $role = 'USER',
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $account = Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Runner Resubmission Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);

        $account->runner_status = $runnerStatus;
        $account->save();

        return $account->fresh();
    }

    private function verifyIdentity(Account $account): void
    {
        IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'verified_name' => 'Contoh Nama Sebenar',
            'mykad_number' => '900101145678',
            'status' => IdentityVerification::STATUS_VERIFIED,
            'submitted_at' => now(),
            'reviewed_at' => now(),
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

    private function authenticate(
        Account $account,
        string $token
    ): void {
        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);
    }

    private function asAccount(
        Account $account,
        string $token
    ): static {
        $this->authenticate($account, $token);

        return $this->withHeader(
            'Authorization',
            'Bearer '.$token
        );
    }

    private function applicationPayload(
        string $licenseNumber
    ): array {
        return [
            'license_number' => $licenseNumber,
            'license_type' => $this->licenseType->code,
            'license_class' => $this->licenseClass->code,
            'expires_at' => now()->addYear()->toDateString(),
            'document' => UploadedFile::fake()->create(
                'lesen.pdf',
                100,
                'application/pdf'
            ),
        ];
    }

    public function test_rejected_runner_application_can_be_resubmitted_without_overwriting_previous_license_record(): void
    {
        $applicant = $this->createAccount();
        $admin = $this->createAccount('SUPER_ADMIN');

        $this->verifyIdentity($applicant);

        $this->grantPermission(
            $admin,
            'runner_applications.reject'
        );

        /*
         * 1. Permohonan pertama.
         */
        $this->asAccount($applicant, 'first-application-token')
            ->post(
                '/api/me/runner-application',
                $this->applicationPayload('D111111111111')
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.runner_application.runner_status',
                Account::RUNNER_STATUS_PENDING
            );

        $firstLicense = RunnerLicenseVerification::query()
            ->where('account_id', $applicant->id)
            ->firstOrFail();

        $this->assertSame(
            RunnerLicenseVerification::STATUS_PENDING,
            $firstLicense->status
        );

        /*
         * 2. Admin menolak permohonan pertama.
         */
        $this->asAccount($admin, 'admin-reject-token')
            ->postJson(
                '/api/admin/runner-applications/'.$applicant->id.'/reject',
                [
                    'reason' => 'Dokumen lesen tidak jelas.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.account.runner_status',
                Account::RUNNER_STATUS_REJECTED
            );

        $firstLicense->refresh();

        $this->assertSame(
            RunnerLicenseVerification::STATUS_REJECTED,
            $firstLicense->status
        );

        $this->assertFalse($firstLicense->is_current);

        /*
         * 3. Pemohon menghantar permohonan semula.
         */
        $this->asAccount($applicant, 'second-application-token')
            ->post(
                '/api/me/runner-application',
                $this->applicationPayload('D222222222222')
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.runner_application.runner_status',
                Account::RUNNER_STATUS_PENDING
            )
            ->assertJsonPath(
                'data.runner_license_verification.status',
                RunnerLicenseVerification::STATUS_PENDING
            );

        /*
         * 4. Rekod pertama mesti kekal REJECTED.
         */
        $firstLicense->refresh();

        $this->assertSame(
            RunnerLicenseVerification::STATUS_REJECTED,
            $firstLicense->status
        );

        $this->assertSame(
            'D111111111111',
            $firstLicense->license_number
        );

        /*
         * 5. Rekod kedua mesti diwujudkan secara berasingan.
         */
        $secondLicense = RunnerLicenseVerification::query()
            ->where('account_id', $applicant->id)
            ->where(
                'status',
                RunnerLicenseVerification::STATUS_PENDING
            )
            ->firstOrFail();

        $this->assertNotSame(
            $firstLicense->id,
            $secondLicense->id
        );

        $this->assertSame(
            'D222222222222',
            $secondLicense->license_number
        );

        $this->assertFalse($secondLicense->is_current);

        /*
         * 6. Pastikan kedua-dua rekod sejarah masih wujud.
         */
        $this->assertDatabaseCount(
            'runner_license_verifications',
            2
        );

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $firstLicense->id,
            'account_id' => $applicant->id,
            'status' => RunnerLicenseVerification::STATUS_REJECTED,
        ]);

        $this->assertDatabaseHas('runner_license_verifications', [
            'id' => $secondLicense->id,
            'account_id' => $applicant->id,
            'status' => RunnerLicenseVerification::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $applicant->id,
            'runner_status' => Account::RUNNER_STATUS_PENDING,
        ]);

        /*
         * 7. Setiap penghantaran direkodkan dalam audit log.
         */
        $this->assertSame(
            2,
            DB::table('audit_logs')
                ->where('actor_account_id', $applicant->id)
                ->where('target_account_id', $applicant->id)
                ->where('action', 'RUNNER_APPLICATION_SUBMITTED')
                ->count()
        );
    }

    public function test_rejected_runner_application_cannot_be_resubmitted_without_verified_identity(): void
    {
        $applicant = $this->createAccount(
            'USER',
            Account::RUNNER_STATUS_REJECTED
        );

        $this->asAccount($applicant, 'identity-required-token')
            ->postJson('/api/me/runner-application')
            ->assertUnprocessable()
            ->assertJsonPath(
                'data.next_action.type',
                'IDENTITY_VERIFICATION'
            );

        $this->assertDatabaseMissing('runner_license_verifications', [
            'account_id' => $applicant->id,
        ]);
    }

    public function test_rejected_runner_application_cannot_submit_duplicate_pending_application(): void
    {
        $applicant = $this->createAccount(
            'USER',
            Account::RUNNER_STATUS_REJECTED
        );

        $this->verifyIdentity($applicant);

        $this->asAccount($applicant, 'first-resubmission-token')
            ->post(
                '/api/me/runner-application',
                $this->applicationPayload('D333333333333')
            )
            ->assertCreated();

        $this->asAccount($applicant, 'duplicate-resubmission-token')
            ->post(
                '/api/me/runner-application',
                $this->applicationPayload('D444444444444')
            )
            ->assertUnprocessable();

        $this->assertDatabaseCount(
            'runner_license_verifications',
            1
        );
    }
}
