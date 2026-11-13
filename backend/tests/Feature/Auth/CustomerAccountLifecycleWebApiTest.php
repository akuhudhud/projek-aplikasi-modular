<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\AuthenticateWebSession;
use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerAccountLifecycleWebApiTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomerWithSessions(
        string $status = 'ACTIVE'
    ): array {
        $account = Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Lifecycle Customer',
            'phone' => '60123456789',
            'email' => 'lifecycle-customer@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => $status,
        ]);

        $tokens = [];

        foreach (range(1, 2) as $index) {
            $token = Str::random(64);

            Session::create([
                'id' => (string) Str::uuid(),
                'account_id' => $account->id,
                'token_hash' => hash('sha256', $token),
                'created_at' => now(),
                'last_activity_at' => now(),
                'ended_at' => null,
                'end_reason' => null,
            ]);

            $tokens[] = $token;
        }

        return [$account, $tokens];
    }

    private function authenticatedSession(string $token): static
    {
        return $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $token,
        ]);
    }

    public function test_customer_can_deactivate_account_through_web_session(): void
    {
        [$account, $tokens] = $this->createCustomerWithSessions();

        $response = $this->authenticatedSession($tokens[0])
            ->postJson('/web/auth/deactivate');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'message',
                'Account deactivated successfully.',
            );

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => 'DEACTIVATED',
        ]);

        $sessions = Session::query()
            ->where('account_id', $account->id)
            ->get();

        $this->assertCount(2, $sessions);

        foreach ($sessions as $session) {
            $this->assertNotNull($session->ended_at);
            $this->assertNotNull($session->end_reason);
        }

        $this->assertDatabaseHas('audit_logs', [
            'target_account_id' => $account->id,
            'action' => 'ACCOUNT_DEACTIVATED',
        ]);
    }

    public function test_customer_can_delete_account_through_web_session(): void
    {
        [$account, $tokens] = $this->createCustomerWithSessions();

        $response = $this->authenticatedSession($tokens[0])
            ->postJson('/web/auth/delete');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'message',
                'Account deleted successfully.',
            );

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => 'DELETED',
        ]);

        $sessions = Session::query()
            ->where('account_id', $account->id)
            ->get();

        $this->assertCount(2, $sessions);

        foreach ($sessions as $session) {
            $this->assertNotNull($session->ended_at);
            $this->assertNotNull($session->end_reason);
        }

        $this->assertDatabaseHas('audit_logs', [
            'target_account_id' => $account->id,
            'action' => 'ACCOUNT_DELETED',
        ]);
    }

    public function test_account_lifecycle_web_routes_require_authentication(): void
    {
        $this->postJson('/web/auth/deactivate')
            ->assertUnauthorized();

        $this->postJson('/web/auth/delete')
            ->assertUnauthorized();
    }

    public function test_deactivation_rejects_non_active_account(): void
    {
        [$account, $tokens] = $this->createCustomerWithSessions(
            'DEACTIVATED'
        );

        $this->authenticatedSession($tokens[0])
            ->postJson('/web/auth/deactivate')
            ->assertUnauthorized();

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => 'DEACTIVATED',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'target_account_id' => $account->id,
            'action' => 'ACCOUNT_DEACTIVATED',
        ]);
    }

    public function test_deletion_rejects_non_active_account(): void
    {
        [$account, $tokens] = $this->createCustomerWithSessions(
            'DELETED'
        );

        $this->authenticatedSession($tokens[0])
            ->postJson('/web/auth/delete')
            ->assertUnauthorized();

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'status' => 'DELETED',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'target_account_id' => $account->id,
            'action' => 'ACCOUNT_DELETED',
        ]);
    }
}
