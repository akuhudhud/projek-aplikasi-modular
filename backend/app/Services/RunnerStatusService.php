<?php

namespace App\Services;

use App\Models\Account;
use App\Models\IdentityVerification;
use App\Models\RunnerLicenseVerification;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class RunnerStatusService
{
    public function __construct(
        private AuditLogService $auditLogService,
        private AdminPermissionService $permissionService,
        private RunnerLicenseVerificationService $licenseService
    ) {
    }

    public function apply(
        Account $applicant,
        string $licenseNumber,
        string $licenseClass,
        string $expiresAt,
        UploadedFile $document,
        ?string $licenseType = null
    ): Account {
        $identityStatus = IdentityVerification::query()
            ->where('account_id', $applicant->id)
            ->value('status');

        if ($identityStatus !== IdentityVerification::STATUS_VERIFIED) {
            throw new DomainException(
                'Identity verification is required before applying to become a Runner.'
            );
        }

        if (!in_array($applicant->role, ['USER', 'SUPER_ADMIN'], true)) {
            throw new AuthorizationException(
                'This account cannot apply to become a Runner.'
            );
        }

        if ($applicant->status !== 'ACTIVE') {
            throw new DomainException(
                'Only active accounts can apply to become a Runner.'
            );
        }

        if (
            $applicant->runner_status
            !== Account::RUNNER_STATUS_NOT_RUNNER
        ) {
            throw new DomainException(
                'A Runner application cannot be submitted from the current status.'
            );
        }

        $this->licenseService->submit(
            $applicant,
            $licenseNumber,
            $licenseClass,
            $expiresAt,
            $document,
            $licenseType,
            function (
                RunnerLicenseVerification $verification,
                Account $lockedAccount
            ): void {
                if (!in_array(
                    $lockedAccount->role,
                    ['USER', 'SUPER_ADMIN'],
                    true
                )) {
                    throw new AuthorizationException(
                        'This account cannot apply to become a Runner.'
                    );
                }

                if ($lockedAccount->status !== 'ACTIVE') {
                    throw new DomainException(
                        'Only active accounts can apply to become a Runner.'
                    );
                }

                if (
                    $lockedAccount->runner_status
                    !== Account::RUNNER_STATUS_NOT_RUNNER
                ) {
                    throw new DomainException(
                        'A Runner application cannot be submitted from the current status.'
                    );
                }

                $lockedAccount->runner_status =
                    Account::RUNNER_STATUS_PENDING;
                $lockedAccount->save();

                // Satu audit sahaja untuk penghantaran permohonan Runner.
                $this->auditLogService->record(
                    $lockedAccount,
                    $lockedAccount,
                    'RUNNER_APPLICATION_SUBMITTED',
                    'Runner application submitted with driving license document.'
                );
            }
        );

        return $applicant->fresh();
    }

    public function pendingApplications(Account $actor): Collection
    {
        $this->assertAdministrator($actor);
        $this->assertPermission($actor, 'runner_applications.view');

        $query = Account::query()
            ->where(
                'runner_status',
                Account::RUNNER_STATUS_PENDING
            )
            ->whereIn('role', ['USER', 'SUPER_ADMIN']);

        if ($actor->role === 'SUPER_ADMIN') {
            $query->where('role', 'USER');
        }

        return $query
            ->orderBy('created_at')
            ->get([
                'id',
                'name',
                'phone',
                'email',
                'profile_picture',
                'role',
                'status',
                'runner_status',
                'created_at',
                'updated_at',
            ]);
    }

    public function changeStatus(
        Account $actor,
        string $targetAccountId,
        string $nextStatus,
        string $reason
    ): Account {
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('Reason is required.');
        }

        $allowedStatuses = [
            Account::RUNNER_STATUS_APPROVED,
            Account::RUNNER_STATUS_REJECTED,
            Account::RUNNER_STATUS_SUSPENDED,
        ];

        if (!in_array($nextStatus, $allowedStatuses, true)) {
            throw new DomainException(
                'The requested Runner status is invalid.'
            );
        }

        return DB::transaction(function () use (
            $actor,
            $targetAccountId,
            $nextStatus,
            $reason
        ): Account {
            $currentActor = Account::query()
                ->whereKey($actor->id)
                ->lockForUpdate()
                ->firstOrFail();

            $target = Account::query()
                ->whereKey($targetAccountId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCanManageTarget($currentActor, $target);

            $currentStatus = $target->runner_status;

            $this->assertValidTransition(
                $currentStatus,
                $nextStatus
            );

            $this->assertPermission(
                $currentActor,
                $this->requiredPermission(
                    $currentStatus,
                    $nextStatus
                )
            );

            if (
                $nextStatus === Account::RUNNER_STATUS_APPROVED
                && $target->status !== 'ACTIVE'
            ) {
                throw new DomainException(
                    'Only active accounts can be approved as a Runner.'
                );
            }

            /*
             * Permohonan pertama:
             * keputusan Runner dan keputusan lesen dibuat bersama.
             *
             * Pemulihan Runner yang telah digantung:
             * gunakan lesen VERIFIED semasa, bukan lesen PENDING baharu.
             */
            if (
                $currentStatus === Account::RUNNER_STATUS_PENDING
                && $nextStatus === Account::RUNNER_STATUS_APPROVED
            ) {
                $this->approveInitialApplication(
                    $currentActor,
                    $target
                );
            } elseif (
                $currentStatus === Account::RUNNER_STATUS_PENDING
                && $nextStatus === Account::RUNNER_STATUS_REJECTED
            ) {
                $this->rejectInitialApplication(
                    $currentActor,
                    $target,
                    $reason
                );
            } elseif (
                $nextStatus === Account::RUNNER_STATUS_APPROVED
            ) {
                $this->assertHasValidCurrentLicense($target);
            }

            $target->runner_status = $nextStatus;
            $target->save();

            // Hanya satu audit untuk keputusan permohonan Runner.
            // Perubahan status lesen di atas sebahagian daripada keputusan ini.
            $this->auditLogService->record(
                $currentActor,
                $target,
                $this->auditAction($currentStatus, $nextStatus),
                $reason
            );

            return $target->refresh();
        });
    }

    private function approveInitialApplication(
        Account $actor,
        Account $target
    ): void {
        $license = RunnerLicenseVerification::query()
            ->where('account_id', $target->id)
            ->where(
                'status',
                RunnerLicenseVerification::STATUS_PENDING
            )
            ->orderByDesc('submitted_at')
            ->lockForUpdate()
            ->first();

        if ($license === null) {
            throw new DomainException(
                'Permohonan tidak mempunyai dokumen lesen PENDING untuk disemak.'
            );
        }

        if (
            $license->expires_at === null
            || $license->expires_at->lt(now()->startOfDay())
        ) {
            throw new DomainException(
                'Lesen telah tamat tempoh. Tolak permohonan Runner dengan sebab yang sesuai.'
            );
        }

        RunnerLicenseVerification::query()
            ->where('account_id', $target->id)
            ->where('license_class', $license->license_class)
            ->where('is_current', true)
            ->where(
                'status',
                RunnerLicenseVerification::STATUS_VERIFIED
            )
            ->where('id', '!=', $license->id)
            ->lockForUpdate()
            ->update(['is_current' => false]);

        $license->status = RunnerLicenseVerification::STATUS_VERIFIED;
        $license->is_current = true;
        $license->reviewer_account_id = $actor->id;
        $license->reviewed_at = now();
        $license->rejection_reason = null;
        $license->save();
    }

    private function rejectInitialApplication(
        Account $actor,
        Account $target,
        string $reason
    ): void {
        $license = RunnerLicenseVerification::query()
            ->where('account_id', $target->id)
            ->where(
                'status',
                RunnerLicenseVerification::STATUS_PENDING
            )
            ->orderByDesc('submitted_at')
            ->lockForUpdate()
            ->first();

        if ($license === null) {
            throw new DomainException(
                'Permohonan tidak mempunyai dokumen lesen PENDING untuk disemak.'
            );
        }

        $license->status = RunnerLicenseVerification::STATUS_REJECTED;
        $license->is_current = false;
        $license->reviewer_account_id = $actor->id;
        $license->reviewed_at = now();
        $license->rejection_reason = $reason;
        $license->save();
    }

    private function assertHasValidCurrentLicense(
        Account $target
    ): void {
        $hasValidLicense = RunnerLicenseVerification::query()
            ->where('account_id', $target->id)
            ->where('is_current', true)
            ->where(
                'status',
                RunnerLicenseVerification::STATUS_VERIFIED
            )
            ->whereNotNull('expires_at')
            ->whereDate(
                'expires_at',
                '>=',
                now()->toDateString()
            )
            ->lockForUpdate()
            ->exists();

        if (!$hasValidLicense) {
            throw new DomainException(
                'Runner tidak boleh diluluskan atau dipulihkan tanpa lesen VERIFIED yang masih sah.'
            );
        }
    }

    private function assertAdministrator(Account $actor): void
    {
        if (!in_array(
            $actor->role,
            ['ROOT_SUPER_ADMIN', 'SUPER_ADMIN'],
            true
        )) {
            throw new AuthorizationException(
                'Only administrators can review Runner applications.'
            );
        }
    }

    private function assertPermission(
        Account $actor,
        string $permission
    ): void {
        if (!$this->permissionService->allows($actor, $permission)) {
            throw new AuthorizationException(
                "Missing required permission: {$permission}."
            );
        }
    }

    private function assertCanManageTarget(
        Account $actor,
        Account $target
    ): void {
        $this->assertAdministrator($actor);

        // Root ialah pemilik dan tidak tertakluk pada sekatan pentadbir dalaman.
        if ($actor->role === 'ROOT_SUPER_ADMIN') {
            return;
        }

        if ($actor->id === $target->id) {
            throw new AuthorizationException(
                'Administrators cannot change their own Runner status.'
            );
        }

        if ($target->role === 'ROOT_SUPER_ADMIN') {
            throw new AuthorizationException(
                'A Root Super Admin Runner status cannot be managed.'
            );
        }

        if ($target->role !== 'USER') {
            throw new AuthorizationException(
                'Super Admin can manage Runner status for USER accounts only.'
            );
        }
    }

    private function requiredPermission(
        string $currentStatus,
        string $nextStatus
    ): string {
        return match ([$currentStatus, $nextStatus]) {
            [
                Account::RUNNER_STATUS_PENDING,
                Account::RUNNER_STATUS_APPROVED,
            ] => 'runner_applications.approve',

            [
                Account::RUNNER_STATUS_PENDING,
                Account::RUNNER_STATUS_REJECTED,
            ] => 'runner_applications.reject',

            [
                Account::RUNNER_STATUS_APPROVED,
                Account::RUNNER_STATUS_SUSPENDED,
            ] => 'runners.suspend',

            [
                Account::RUNNER_STATUS_SUSPENDED,
                Account::RUNNER_STATUS_APPROVED,
            ] => 'runners.restore',

            default => throw new DomainException(
                'The requested Runner status transition is not allowed.'
            ),
        };
    }

    private function assertValidTransition(
        string $currentStatus,
        string $nextStatus
    ): void {
        $valid = match ($currentStatus) {
            Account::RUNNER_STATUS_PENDING => in_array(
                $nextStatus,
                [
                    Account::RUNNER_STATUS_APPROVED,
                    Account::RUNNER_STATUS_REJECTED,
                ],
                true
            ),

            Account::RUNNER_STATUS_APPROVED =>
                $nextStatus === Account::RUNNER_STATUS_SUSPENDED,

            Account::RUNNER_STATUS_SUSPENDED =>
                $nextStatus === Account::RUNNER_STATUS_APPROVED,

            default => false,
        };

        if (!$valid) {
            throw new DomainException(
                'The requested Runner status transition is not allowed.'
            );
        }
    }

    private function auditAction(
        string $currentStatus,
        string $nextStatus
    ): string {
        return match ([$currentStatus, $nextStatus]) {
            [
                Account::RUNNER_STATUS_PENDING,
                Account::RUNNER_STATUS_APPROVED,
            ] => 'RUNNER_APPLICATION_APPROVED',

            [
                Account::RUNNER_STATUS_PENDING,
                Account::RUNNER_STATUS_REJECTED,
            ] => 'RUNNER_APPLICATION_REJECTED',

            [
                Account::RUNNER_STATUS_APPROVED,
                Account::RUNNER_STATUS_SUSPENDED,
            ] => 'RUNNER_ELIGIBILITY_SUSPENDED',

            [
                Account::RUNNER_STATUS_SUSPENDED,
                Account::RUNNER_STATUS_APPROVED,
            ] => 'RUNNER_ELIGIBILITY_RESTORED',

            default => throw new DomainException(
                'The requested Runner status transition is not allowed.'
            ),
        };
    }
}
