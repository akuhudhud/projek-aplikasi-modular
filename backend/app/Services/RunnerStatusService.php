<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RunnerStatusService
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {
    }

    public function apply(Account $applicant): Account
    {
        return DB::transaction(function () use ($applicant): Account {
            $account = Account::query()
                ->whereKey($applicant->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($account->role, ['USER', 'SUPER_ADMIN'], true)) {
                throw new AuthorizationException(
                    'This account cannot apply to become a Runner.'
                );
            }

            if ($account->status !== 'ACTIVE') {
                throw new DomainException(
                    'Only active accounts can apply to become a Runner.'
                );
            }

            if (
                $account->runner_status
                !== Account::RUNNER_STATUS_NOT_RUNNER
            ) {
                throw new DomainException(
                    'A Runner application cannot be submitted from the current status.'
                );
            }

            $account->runner_status = Account::RUNNER_STATUS_PENDING;
            $account->save();

            $this->auditLogService->record(
                $account,
                $account,
                'RUNNER_APPLICATION_SUBMITTED',
                'Runner application submitted by account owner.'
            );

            return $account->refresh();
        });
    }

    public function pendingApplications(Account $actor): Collection
    {
        $this->assertAdministrator($actor);

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

            // Only ACTIVE accounts may be approved or restored as Runners.
            if (
                $nextStatus === Account::RUNNER_STATUS_APPROVED
                && $target->status !== 'ACTIVE'
            ) {
                throw new DomainException(
                    'Only active accounts can be approved as a Runner.'
                );
            }

            $currentStatus = $target->runner_status;

            $this->assertValidTransition(
                $currentStatus,
                $nextStatus
            );

            $target->runner_status = $nextStatus;
            $target->save();

            $this->auditLogService->record(
                $currentActor,
                $target,
                $this->auditAction($currentStatus, $nextStatus),
                $reason
            );

            return $target->refresh();
        });
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

    private function assertCanManageTarget(
        Account $actor,
        Account $target
    ): void {
        $this->assertAdministrator($actor);

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

        if (!in_array($target->role, ['USER', 'SUPER_ADMIN'], true)) {
            throw new AuthorizationException(
                'The target account role cannot be managed.'
            );
        }

        if (
            $actor->role === 'SUPER_ADMIN'
            && $target->role !== 'USER'
        ) {
            throw new AuthorizationException(
                'Super Admin can manage Runner status for USER accounts only.'
            );
        }
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
