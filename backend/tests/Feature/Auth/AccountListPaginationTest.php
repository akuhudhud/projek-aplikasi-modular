<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountListPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $name,
        string $phone,
        string $email,
        string $role
    ): Account {
        return Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => $name,
            'phone' => $phone,
            'email' => $email,
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function createRootSession(): string
    {
        $root = $this->createAccount(
            'Root Admin',
            '60120000001',
            'root-pagination@example.com',
            'ROOT_SUPER_ADMIN'
        );

        $token = 'account-pagination-test-token';

        Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $root->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return $token;
    }

    public function test_account_list_returns_requested_page_and_metadata(): void
    {
        $token = $this->createRootSession();

        for ($i = 1; $i <= 16; $i++) {
            $number = str_pad((string) $i, 7, '0', STR_PAD_LEFT);

            $this->createAccount(
                "Test User {$i}",
                "6013{$number}",
                "user{$i}@pagination.example.com",
                'USER'
            );
        }

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/accounts?page=2&per_page=5');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.pagination.last_page', 4)
            ->assertJsonPath('data.pagination.per_page', 5)
            ->assertJsonPath('data.pagination.total', 17)
            ->assertJsonPath('data.pagination.from', 6)
            ->assertJsonPath('data.pagination.to', 10);

        $this->assertCount(
            5,
            $response->json('data.accounts')
        );
    }

    public function test_account_list_uses_default_pagination_values(): void
    {
        $token = $this->createRootSession();

        for ($i = 1; $i <= 17; $i++) {
            $number = str_pad((string) $i, 7, '0', STR_PAD_LEFT);

            $this->createAccount(
                "Default User {$i}",
                "6014{$number}",
                "default{$i}@pagination.example.com",
                'USER'
            );
        }

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/accounts');

        $response->assertOk()
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonPath('data.pagination.last_page', 2)
            ->assertJsonPath('data.pagination.per_page', 15)
            ->assertJsonPath('data.pagination.total', 18);

        $this->assertCount(
            15,
            $response->json('data.accounts')
        );
    }

    public function test_account_list_rejects_per_page_above_limit(): void
    {
        $token = $this->createRootSession();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/accounts?per_page=101');

        $response->assertUnprocessable();
    }

    public function test_account_list_rejects_invalid_page_number(): void
    {
        $token = $this->createRootSession();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->getJson('/api/admin/accounts?page=0');

        $response->assertUnprocessable();
    }
}
