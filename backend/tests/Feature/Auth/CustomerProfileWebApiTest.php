<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\AuthenticateWebSession;
use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerProfileWebApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(): Account
    {
        return Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Original Customer',
            'phone' => '60123456789',
            'email' => 'customer@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);
    }

    private function authenticatedRequest(Account $account): static
    {
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

        return $this->withSession([
            AuthenticateWebSession::SESSION_TOKEN_KEY => $token,
        ]);
    }

    public function test_authenticated_customer_can_update_profile_name(): void
    {
        $account = $this->createAccount();

        $response = $this->authenticatedRequest($account)
            ->patchJson('/web/auth/profile', [
                'profile_name' => 'Updated Customer',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account.profile_name', 'Updated Customer')
            ->assertJsonPath('data.account.id', $account->id);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'profile_name' => 'Updated Customer',
        ]);
    }

    public function test_profile_update_requires_web_authentication(): void
    {
        $this->patchJson('/web/auth/profile', [
            'profile_name' => 'Updated Customer',
        ])->assertUnauthorized();
    }

    public function test_profile_update_rejects_name_longer_than_255_characters(): void
    {
        $account = $this->createAccount();

        $response = $this->authenticatedRequest($account)
            ->patchJson('/web/auth/profile', [
                'profile_name' => str_repeat('A', 256),
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['profile_name']);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'profile_name' => 'Original Customer',
        ]);
    }

    public function test_profile_update_rejects_non_string_name(): void
    {
        $account = $this->createAccount();

        $response = $this->authenticatedRequest($account)
            ->patchJson('/web/auth/profile', [
                'profile_name' => ['Invalid'],
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['profile_name']);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'profile_name' => 'Original Customer',
        ]);
    }

    public function test_authenticated_customer_can_upload_profile_picture(): void
    {
        Storage::fake('public');

        $account = $this->createAccount();

        $file = UploadedFile::fake()->image('profile.jpg');

        $response = $this->authenticatedRequest($account)
            ->post('/web/auth/profile-picture', [
                'profile_picture' => $file,
            ], [
                'Accept' => 'application/json',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $path = $account->fresh()->profile_picture;

        $this->assertNotNull($path);

        $response->assertJsonPath(
            'data.account.profile_picture',
            $path
        );

        Storage::disk('public')->assertExists($path);
    }

    public function test_profile_picture_upload_requires_web_authentication(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('profile.jpg');

        $response = $this->post('/web/auth/profile-picture', [
            'profile_picture' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertUnauthorized();
    }

    public function test_profile_picture_upload_rejects_invalid_file(): void
    {
        Storage::fake('public');

        $account = $this->createAccount();

        $file = UploadedFile::fake()->create(
            'document.pdf',
            100,
            'application/pdf'
        );

        $response = $this->authenticatedRequest($account)
            ->post('/web/auth/profile-picture', [
                'profile_picture' => $file,
            ], [
                'Accept' => 'application/json',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['profile_picture']);

        $this->assertNull($account->fresh()->profile_picture);
    }

    public function test_profile_picture_upload_rejects_file_over_5_mb(): void
    {
        Storage::fake('public');

        $account = $this->createAccount();

        $file = UploadedFile::fake()->image('profile.jpg')
            ->size(5121);

        $response = $this->authenticatedRequest($account)
            ->post('/web/auth/profile-picture', [
                'profile_picture' => $file,
            ], [
                'Accept' => 'application/json',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['profile_picture']);

        $this->assertNull($account->fresh()->profile_picture);
    }
}
