<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RootSuperAdminJobTitleTest extends TestCase
{
    use RefreshDatabase;

    private function createRootWithSession(): array
    {
        $root = Account::create([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Root Owner',
            'phone' => '60111111111',
            'email' => 'root@example.com',
            'password' => 'Password1',
            'role' => 'ROOT_SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $token = 'root-job-title-test-token';

        Session::create([
            'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'account_id' => $root->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return [$root, $token];
    }

    public function test_root_can_appoint_super_admin_with_job_title(): void
    {
        [$root, $token] = $this->createRootWithSession();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson('/api/admin/accounts', [
            'name' => 'Ahmad',
            'job_title' => 'Pengurus Akaun',
            'phone' => '60222222222',
            'email' => 'ahmad@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'reason' => 'Pelantikan Pengurus Akaun.',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.account.name', 'Ahmad')
            ->assertJsonPath(
                'data.account.job_title',
                'Pengurus Akaun'
            )
            ->assertJsonPath(
                'data.account.role',
                'SUPER_ADMIN'
            );

        $this->assertDatabaseHas('accounts', [
            'email' => 'ahmad@example.com',
            'name' => 'Ahmad',
            'job_title' => 'Pengurus Akaun',
            'role' => 'SUPER_ADMIN',
        ]);
    }

    public function test_root_can_update_super_admin_job_title(): void
    {
        [$root, $token] = $this->createRootWithSession();

        $admin = Account::create([
            'id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'name' => 'Ali',
            'job_title' => 'Pengurus Kawasan',
            'phone' => '60333333333',
            'email' => 'ali@example.com',
            'password' => 'Password1',
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->patchJson(
            '/api/admin/accounts/'.$admin->id.'/job-title',
            [
                'job_title' => 'Pengurus Operasi',
                'reason' => 'Perubahan penempatan tugas.',
            ]
        );

        $response
            ->assertStatus(200)
            ->assertJsonPath(
                'data.account.job_title',
                'Pengurus Operasi'
            )
            ->assertJsonPath(
                'data.account.role',
                'SUPER_ADMIN'
            );

        $this->assertDatabaseHas('accounts', [
            'id' => $admin->id,
            'job_title' => 'Pengurus Operasi',
            'role' => 'SUPER_ADMIN',
        ]);
    }

    public function test_root_can_clear_super_admin_job_title(): void
    {
        [$root, $token] = $this->createRootWithSession();

        $admin = Account::create([
            'id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'name' => 'Ali',
            'job_title' => 'Pengurus Kawasan',
            'phone' => '60444444444',
            'email' => 'ali-clear@example.com',
            'password' => 'Password1',
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->patchJson(
            '/api/admin/accounts/'.$admin->id.'/job-title',
            [
                'job_title' => null,
                'reason' => 'Jawatan dikosongkan.',
            ]
        )->assertStatus(200)
            ->assertJsonPath('data.account.job_title', null);

        $this->assertDatabaseHas('accounts', [
            'id' => $admin->id,
            'job_title' => null,
        ]);
    }

    public function test_super_admin_cannot_assign_job_titles(): void
    {
        $admin = Account::create([
            'id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            'name' => 'Super Admin',
            'phone' => '60555555555',
            'email' => 'superadmin@example.com',
            'password' => 'Password1',
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $token = 'super-admin-job-title-test-token';

        Session::create([
            'id' => 'ffffffff-ffff-4fff-8fff-ffffffffffff',
            'account_id' => $admin->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        $target = Account::create([
            'id' => '11111111-1111-4111-8111-111111111111',
            'name' => 'Target Admin',
            'phone' => '60666666666',
            'email' => 'target-admin@example.com',
            'password' => 'Password1',
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->patchJson(
            '/api/admin/accounts/'.$target->id.'/job-title',
            [
                'job_title' => 'Pengurus Kewangan',
                'reason' => 'Percubaan mengubah jawatan.',
            ]
        )->assertStatus(403);

        $this->assertDatabaseHas('accounts', [
            'id' => $target->id,
            'job_title' => null,
        ]);
    }
}
