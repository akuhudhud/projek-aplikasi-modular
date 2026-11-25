
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
            'profile_name' => 'Root Owner',
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

    private function createUser(): Account
    {
        return Account::create([
            'id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'profile_name' => 'Ahmad',
            'phone' => '60222222222',
            'email' => 'ahmad@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);
    }

    private function createSession(
        Account $account,
        string $token,
        string $sessionId
    ): void {
        Session::create([
            'id' => $sessionId,
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);
    }

    public function test_root_can_appoint_existing_user_without_creating_new_account(): void
    {
        [$root, $token] = $this->createRootWithSession();
        $user = $this->createUser();

        $originalId = $user->id;
        $originalPassword = $user->password;
        $originalPhone = $user->phone;
        $originalEmail = $user->email;

        $userToken = 'existing-user-session-token';

        $this->createSession(
            $user,
            $userToken,
            'dddddddd-dddd-4ddd-8ddd-dddddddddddd'
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson(
            '/api/admin/accounts/'.$user->id.'/appoint-super-admin',
            [
                'job_title' => 'Pengurus Akaun',
                'reason' => 'Pelantikan Pengurus Akaun.',
            ]
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Super Admin appointed successfully.',
            ])
            ->assertJsonPath('data.account.id', $originalId)
            ->assertJsonPath('data.account.profile_name', 'Ahmad')
            ->assertJsonPath('data.account.job_title', 'Pengurus Akaun')
            ->assertJsonPath('data.account.phone', $originalPhone)
            ->assertJsonPath('data.account.email', $originalEmail)
            ->assertJsonPath('data.account.role', 'SUPER_ADMIN')
            ->assertJsonPath('data.account.status', 'ACTIVE')
            ->assertJsonMissingPath('data.account.password')
            ->assertJsonMissingPath('data.account.token_hash');

        $this->assertDatabaseCount('accounts', 2);

        $this->assertDatabaseHas('accounts', [
            'id' => $originalId,
            'profile_name' => 'Ahmad',
            'phone' => $originalPhone,
            'email' => $originalEmail,
            'password' => $originalPassword,
            'job_title' => 'Pengurus Akaun',
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('sessions', [
            'id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'account_id' => $originalId,
            'token_hash' => hash('sha256', $userToken),
            'ended_at' => null,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_account_id' => $root->id,
            'target_account_id' => $originalId,
            'action' => 'SUPER_ADMIN_APPOINTED',
            'reason' => 'Pelantikan Pengurus Akaun.',
        ]);
    }

    public function test_root_cannot_appoint_another_super_admin_as_user(): void
    {
        [$root, $token] = $this->createRootWithSession();

        $admin = Account::create([
            'id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
            'profile_name' => 'Existing Admin',
            'phone' => '60333333333',
            'email' => 'existing-admin@example.com',
            'password' => 'Password1',
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson(
            '/api/admin/accounts/'.$admin->id.'/appoint-super-admin',
            [
                'job_title' => 'Pengurus Operasi',
                'reason' => 'Percubaan pelantikan semula.',
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseHas('accounts', [
            'id' => $admin->id,
            'role' => 'SUPER_ADMIN',
            'job_title' => null,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $root->id,
            'target_account_id' => $admin->id,
            'action' => 'SUPER_ADMIN_APPOINTED',
        ]);
    }

    public function test_root_cannot_appoint_inactive_user(): void
    {
        [$root, $token] = $this->createRootWithSession();

        $user = Account::create([
            'id' => 'ffffffff-ffff-4fff-8fff-ffffffffffff',
            'profile_name' => 'Inactive User',
            'phone' => '60444444444',
            'email' => 'inactive@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'SUSPENDED',
        ]);

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson(
            '/api/admin/accounts/'.$user->id.'/appoint-super-admin',
            [
                'job_title' => 'Pengurus Operasi',
                'reason' => 'Pelantikan pengguna tidak aktif.',
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseHas('accounts', [
            'id' => $user->id,
            'role' => 'USER',
            'status' => 'SUSPENDED',
        ]);
    }

    public function test_appointment_requires_reason(): void
    {
        [$root, $token] = $this->createRootWithSession();
        $user = $this->createUser();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson(
            '/api/admin/accounts/'.$user->id.'/appoint-super-admin',
            [
                'job_title' => 'Pengurus Akaun',
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->assertDatabaseHas('accounts', [
            'id' => $user->id,
            'role' => 'USER',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'actor_account_id' => $root->id,
            'target_account_id' => $user->id,
            'action' => 'SUPER_ADMIN_APPOINTED',
        ]);
    }

    public function test_appointment_rejects_blank_reason(): void
    {
        [$root, $token] = $this->createRootWithSession();
        $user = $this->createUser();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson(
            '/api/admin/accounts/'.$user->id.'/appoint-super-admin',
            [
                'job_title' => 'Pengurus Akaun',
                'reason' => '   ',
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->assertDatabaseHas('accounts', [
            'id' => $user->id,
            'role' => 'USER',
        ]);
    }

    public function test_appointment_requires_job_title(): void
    {
        [$root, $token] = $this->createRootWithSession();
        $user = $this->createUser();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson(
            '/api/admin/accounts/'.$user->id.'/appoint-super-admin',
            [
                'reason' => 'Pelantikan pentadbir.',
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('job_title');

        $this->assertDatabaseHas('accounts', [
            'id' => $user->id,
            'role' => 'USER',
        ]);
    }

    public function test_appointment_requires_authenticated_session(): void
    {
        $user = $this->createUser();

        $this->postJson(
            '/api/admin/accounts/'.$user->id.'/appoint-super-admin',
            [
                'job_title' => 'Pengurus Akaun',
                'reason' => 'Pelantikan pentadbir.',
            ]
        )->assertStatus(401);

        $this->assertDatabaseHas('accounts', [
            'id' => $user->id,
            'role' => 'USER',
        ]);
    }

    public function test_super_admin_cannot_appoint_another_user(): void
    {
        $admin = Account::create([
            'id' => '11111111-1111-4111-8111-111111111111',
            'profile_name' => 'Super Admin',
            'phone' => '60555555555',
            'email' => 'superadmin@example.com',
            'password' => 'Password1',
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $token = 'super-admin-appointment-token';

        $this->createSession(
            $admin,
            $token,
            '22222222-2222-4222-8222-222222222222'
        );

        $user = Account::create([
            'id' => '33333333-3333-4333-8333-333333333333',
            'profile_name' => 'Target User',
            'phone' => '60666666666',
            'email' => 'target-user@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->postJson(
            '/api/admin/accounts/'.$user->id.'/appoint-super-admin',
            [
                'job_title' => 'Pengurus Operasi',
                'reason' => 'Percubaan tanpa kebenaran.',
            ]
        )->assertStatus(403);

        $this->assertDatabaseHas('accounts', [
            'id' => $user->id,
            'role' => 'USER',
        ]);
    }

    public function test_root_can_update_super_admin_job_title(): void
    {
        [$root, $token] = $this->createRootWithSession();

        $admin = Account::create([
            'id' => '44444444-4444-4444-8444-444444444444',
            'profile_name' => 'Ali',
            'job_title' => 'Pengurus Kawasan',
            'phone' => '60777777777',
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
            )
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'account' => [
                        'id',
                        'profile_name',
                        'job_title',
                        'phone',
                        'email',
                        'profile_picture',
                        'role',
                        'status',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ])
            ->assertJsonMissingPath('data.account.password')
            ->assertJsonMissingPath('data.account.reason')
            ->assertJsonMissingPath('data.account.token_hash')
            ->assertJsonMissingPath('data.account.failed_login_attempts')
            ->assertJsonMissingPath('data.account.locked_until');

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
            'id' => '55555555-5555-4555-8555-555555555555',
            'profile_name' => 'Ali',
            'job_title' => 'Pengurus Kawasan',
            'phone' => '60888888888',
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
            ->assertJsonPath('data.account.job_title', null)
            ->assertJsonMissingPath('data.account.failed_login_attempts')
            ->assertJsonMissingPath('data.account.locked_until');

        $this->assertDatabaseHas('accounts', [
            'id' => $admin->id,
            'job_title' => null,
        ]);
    }

    public function test_super_admin_cannot_assign_job_titles(): void
    {
        $admin = Account::create([
            'id' => '66666666-6666-4666-8666-666666666666',
            'profile_name' => 'Super Admin',
            'phone' => '60999999999',
            'email' => 'superadmin-job-title@example.com',
            'password' => 'Password1',
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $token = 'super-admin-job-title-test-token';

        $this->createSession(
            $admin,
            $token,
            '77777777-7777-4777-8777-777777777777'
        );

        $target = Account::create([
            'id' => '88888888-8888-4888-8888-888888888888',
            'profile_name' => 'Target Admin',
            'phone' => '60101010101',
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
