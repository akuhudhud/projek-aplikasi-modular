<?php

namespace Tests\Feature\Core;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerApplicationApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $role = 'USER',
        string $status = 'ACTIVE',
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $account = Account::create([
            'id' => (string) Str::uuid(),
            'name' => 'Runner API Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => $role,
            'status' => $status,
        ]);

        $account->runner_status = $runnerStatus;
        $account->save();

        return $account->fresh();
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

    public function test_runner_application_status_requires_authentication(): void
    {
        $this->getJson('/api/me/runner-application')
            ->assertUnauthorized();
    }

    public function test_user_can_submit_runner_application_through_api(): void
    {
        $user = $this->createAccount();

        $this->asAccount($user, 'runner-apply-token')
            ->postJson('/api/me/runner-application')
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.runner_application.runner_status',
                Account::RUNNER_STATUS_PENDING
            );

        $this->assertDatabaseHas('accounts', [
            'id' => $user->id,
            'role' => 'USER',
            'runner_status' => Account::RUNNER_STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $user->id,
            'target_account_id' => $user->id,
            'action' => 'RUNNER_APPLICATION_SUBMITTED',
        ]);
    }

    public function test_duplicate_runner_application_returns_unprocessable(): void
    {
        $user = $this->createAccount(
            'USER',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $this->asAccount($user, 'runner-duplicate-token')
            ->postJson('/api/me/runner-application')
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_user_can_view_own_runner_application_status(): void
    {
        $user = $this->createAccount(
            'USER',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $this->asAccount($user, 'runner-status-token')
            ->getJson('/api/me/runner-application')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.runner_application.account_id',
                $user->id
            )
            ->assertJsonPath(
                'data.runner_application.runner_status',
                Account::RUNNER_STATUS_PENDING
            );
    }

    public function test_admin_can_list_pending_runner_applications(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $applicant = $this->createAccount(
            'USER',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $this->asAccount($admin, 'runner-list-token')
            ->getJson('/api/admin/runner-applications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'id' => $applicant->id,
                'runner_status' => Account::RUNNER_STATUS_PENDING,
            ]);
    }

    public function test_regular_user_cannot_list_runner_applications(): void
    {
        $user = $this->createAccount();

        $this->asAccount($user, 'runner-list-denied-token')
            ->getJson('/api/admin/runner-applications')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_approve_runner_application_with_reason(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $applicant = $this->createAccount(
            'USER',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $this->asAccount($admin, 'runner-approve-token')
            ->postJson(
                '/api/admin/runner-applications/'.$applicant->id.'/approve',
                ['reason' => 'Application reviewed and approved.']
            )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.account.runner_status',
                Account::RUNNER_STATUS_APPROVED
            );

        $this->assertDatabaseHas('accounts', [
            'id' => $applicant->id,
            'runner_status' => Account::RUNNER_STATUS_APPROVED,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $admin->id,
            'target_account_id' => $applicant->id,
            'action' => 'RUNNER_APPLICATION_APPROVED',
            'reason' => 'Application reviewed and approved.',
        ]);
    }

    public function test_admin_decision_requires_reason(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $applicant = $this->createAccount(
            'USER',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $this->asAccount($admin, 'runner-no-reason-token')
            ->postJson(
                '/api/admin/runner-applications/'.$applicant->id.'/approve'
            )
            ->assertUnprocessable();

        $this->assertDatabaseHas('accounts', [
            'id' => $applicant->id,
            'runner_status' => Account::RUNNER_STATUS_PENDING,
        ]);
    }

    public function test_super_admin_cannot_approve_another_super_admin(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $applicant = $this->createAccount(
            'SUPER_ADMIN',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $this->asAccount($admin, 'runner-hierarchy-token')
            ->postJson(
                '/api/admin/runner-applications/'.$applicant->id.'/approve',
                ['reason' => 'Attempted approval.']
            )
            ->assertForbidden();

        $this->assertDatabaseHas('accounts', [
            'id' => $applicant->id,
            'runner_status' => Account::RUNNER_STATUS_PENDING,
        ]);
    }

    public function test_admin_can_reject_application_and_suspend_then_restore_runner(): void
    {
        $admin = $this->createAccount('SUPER_ADMIN');

        $applicant = $this->createAccount(
            'USER',
            'ACTIVE',
            Account::RUNNER_STATUS_PENDING
        );

        $this->asAccount($admin, 'runner-reject-token')
            ->postJson(
                '/api/admin/runner-applications/'.$applicant->id.'/reject',
                ['reason' => 'Application did not meet requirements.']
            )
            ->assertOk()
            ->assertJsonPath(
                'data.account.runner_status',
                Account::RUNNER_STATUS_REJECTED
            );

        $runner = $this->createAccount(
            'USER',
            'ACTIVE',
            Account::RUNNER_STATUS_APPROVED
        );

        $this->asAccount($admin, 'runner-suspend-token')
            ->postJson(
                '/api/admin/runners/'.$runner->id.'/suspend',
                ['reason' => 'Eligibility temporarily suspended.']
            )
            ->assertOk()
            ->assertJsonPath(
                'data.account.runner_status',
                Account::RUNNER_STATUS_SUSPENDED
            );

        $this->asAccount($admin, 'runner-restore-token')
            ->postJson(
                '/api/admin/runners/'.$runner->id.'/restore',
                ['reason' => 'Eligibility restored after review.']
            )
            ->assertOk()
            ->assertJsonPath(
                'data.account.runner_status',
                Account::RUNNER_STATUS_APPROVED
            );

        $this->assertDatabaseHas('audit_logs', [
            'target_account_id' => $applicant->id,
            'action' => 'RUNNER_APPLICATION_REJECTED',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'target_account_id' => $runner->id,
            'action' => 'RUNNER_ELIGIBILITY_SUSPENDED',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'target_account_id' => $runner->id,
            'action' => 'RUNNER_ELIGIBILITY_RESTORED',
        ]);
    }
}
