<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\AuditLog;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditLogPaginationApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $name,
        string $role = 'USER'
    ): Account {
        return Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => $name,
            'phone' => null,
            'email' => Str::uuid().'@example.test',
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function authenticate(Account $account): static
    {
        $token = 'audit-log-'.Str::uuid();

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

    private function grantAuditPermission(Account $account): void
    {
        AccountPermission::create([
            'account_id' => $account->id,
            'permission_key' => 'audit_logs.view',
        ]);
    }

    public function test_audit_log_requires_authentication(): void
    {
        $this->getJson('/api/admin/audit-logs')
            ->assertUnauthorized();
    }

    public function test_super_admin_without_permission_cannot_view_audit_logs(): void
    {
        $admin = $this->createAccount(
            'Admin Tanpa Permission',
            'SUPER_ADMIN'
        );

        $this->authenticate($admin)
            ->getJson('/api/admin/audit-logs')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_audit_log_returns_paginated_records(): void
    {
        $admin = $this->createAccount(
            'Admin Audit',
            'SUPER_ADMIN'
        );

        $this->grantAuditPermission($admin);

        $target = $this->createAccount('Pengguna Sasaran');

        for ($index = 1; $index <= 30; $index++) {
            AuditLog::create([
                'actor_account_id' => $admin->id,
                'target_account_id' => $target->id,
                'action' => 'AUDIT_TEST_'.$index,
                'reason' => 'Ujian rekod audit '.$index,
            ]);
        }

        $response = $this->authenticate($admin)
            ->getJson('/api/admin/audit-logs?per_page=10&page=2');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.pagination.last_page', 3)
            ->assertJsonPath('data.pagination.per_page', 10)
            ->assertJsonPath('data.pagination.total', 30);

        $this->assertCount(
            10,
            $response->json('data.audit_logs')
        );

        $response->assertJsonStructure([
            'data' => [
                'audit_logs' => [
                    '*' => [
                        'id',
                        'actor_account_id',
                        'target_account_id',
                        'action',
                        'reason',
                        'created_at',
                    ],
                ],
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                    'from',
                    'to',
                ],
            ],
        ]);
    }

    public function test_audit_log_defaults_to_twenty_five_records_per_page(): void
    {
        $root = $this->createAccount(
            'Root Audit',
            'ROOT_SUPER_ADMIN'
        );

        $target = $this->createAccount('Target Audit');

        for ($index = 1; $index <= 30; $index++) {
            AuditLog::create([
                'actor_account_id' => $root->id,
                'target_account_id' => $target->id,
                'action' => 'AUDIT_DEFAULT_'.$index,
                'reason' => 'Ujian default pagination',
            ]);
        }

        $response = $this->authenticate($root)
            ->getJson('/api/admin/audit-logs');

        $response->assertOk()
            ->assertJsonPath('data.pagination.per_page', 25)
            ->assertJsonPath('data.pagination.total', 30);

        $this->assertCount(
            25,
            $response->json('data.audit_logs')
        );
    }

    public function test_audit_log_rejects_invalid_pagination_values(): void
    {
        $root = $this->createAccount(
            'Root Audit Validation',
            'ROOT_SUPER_ADMIN'
        );

        $this->authenticate($root)
            ->getJson('/api/admin/audit-logs?page=0&per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page',
                'per_page',
            ]);
    }
}
