<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createAccountWithSession(): array
    {
        $account = Account::create([
            'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'name' => 'Original Name',
            'phone' => '60111111111',
            'email' => 'original@example.com',
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $token = 'profile-management-session-token';

        Session::create([
            'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return [$account, $token];
    }

    private function fakeImage(
        string $name = 'profile.jpg',
        int $kilobytes = 100
    ): UploadedFile {
        return UploadedFile::fake()->create(
            $name,
            $kilobytes,
            'image/jpeg'
        );
    }

    public function test_user_can_update_name_only(): void
    {
        [$account, $token] = $this->createAccountWithSession();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->patchJson('/api/me', [
            'name' => 'Updated Name',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account.name', 'Updated Name')
            ->assertJsonPath('data.account.phone', '60111111111')
            ->assertJsonPath('data.account.email', 'original@example.com');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Updated Name',
            'phone' => '60111111111',
            'email' => 'original@example.com',
        ]);
    }

    public function test_user_cannot_update_phone_through_profile_endpoint(): void
    {
        [$account, $token] = $this->createAccountWithSession();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->patchJson('/api/me', [
            'phone' => '60222222222',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Original Name',
            'phone' => '60111111111',
            'email' => 'original@example.com',
        ]);
    }

    public function test_user_cannot_update_email_through_profile_endpoint(): void
    {
        [$account, $token] = $this->createAccountWithSession();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->patchJson('/api/me', [
            'email' => 'updated@example.com',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Original Name',
            'phone' => '60111111111',
            'email' => 'original@example.com',
        ]);
    }

    public function test_user_cannot_update_phone_and_email_through_profile_endpoint(): void
    {
        [$account, $token] = $this->createAccountWithSession();

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->patchJson('/api/me', [
            'name' => 'Updated Name',
            'phone' => '60222222222',
            'email' => 'updated@example.com',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.account.name', 'Updated Name')
            ->assertJsonPath('data.account.phone', '60111111111')
            ->assertJsonPath('data.account.email', 'original@example.com');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Updated Name',
            'phone' => '60111111111',
            'email' => 'original@example.com',
        ]);
    }

    public function test_update_profile_requires_session_token(): void
    {
        $response = $this->patchJson('/api/me', [
            'name' => 'Updated Name',
        ]);

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_update_profile_rejects_invalid_session_token(): void
    {
        $response = $this->withHeader(
            'Authorization',
            'Bearer invalid-session-token'
        )->patchJson('/api/me', [
            'name' => 'Updated Name',
        ]);

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_user_can_update_profile_picture(): void
    {
        Storage::fake('public');

        [$account, $token] = $this->createAccountWithSession();

        $file = $this->fakeImage();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->post('/api/me/profile-picture', [
            'profile_picture' => $file,
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $path = $account->fresh()->profile_picture;

        $this->assertNotNull($path);

        $response->assertJsonPath(
            'data.account.profile_picture',
            $path
        );

        Storage::disk('public')->assertExists($path);
    }

    public function test_user_can_replace_existing_profile_picture(): void
    {
        Storage::fake('public');

        [$account, $token] = $this->createAccountWithSession();

        $oldFile = $this->fakeImage('old-profile.jpg');

        $oldPath = $oldFile->store(
            'profile-pictures/'.$account->id,
            'public'
        );

        $account->profile_picture = $oldPath;
        $account->save();

        Storage::disk('public')->assertExists($oldPath);

        $newFile = $this->fakeImage('new-profile.jpg');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->post('/api/me/profile-picture', [
            'profile_picture' => $newFile,
        ]);

        $response->assertStatus(200);

        $newPath = $account->fresh()->profile_picture;

        $this->assertNotNull($newPath);
        $this->assertNotSame($oldPath, $newPath);

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_profile_picture_requires_image_file(): void
    {
        Storage::fake('public');

        [$account, $token] = $this->createAccountWithSession();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->post('/api/me/profile-picture', [
            'profile_picture' => 'not-an-image',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'profile_picture',
            ]);

        $this->assertNull(
            $account->fresh()->profile_picture
        );
    }

    public function test_profile_picture_rejects_unsupported_file_type(): void
    {
        Storage::fake('public');

        [$account, $token] = $this->createAccountWithSession();

        $file = UploadedFile::fake()->create(
            'profile.pdf',
            100,
            'application/pdf'
        );

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->post('/api/me/profile-picture', [
            'profile_picture' => $file,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'profile_picture',
            ]);

        $this->assertNull(
            $account->fresh()->profile_picture
        );
    }

    public function test_profile_picture_rejects_file_over_5_mb(): void
    {
        Storage::fake('public');

        [$account, $token] = $this->createAccountWithSession();

        $file = UploadedFile::fake()->create(
            'profile.jpg',
            5121,
            'image/jpeg'
        );

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->post('/api/me/profile-picture', [
            'profile_picture' => $file,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'profile_picture',
            ]);

        $this->assertNull(
            $account->fresh()->profile_picture
        );
    }

    public function test_profile_picture_requires_session(): void
    {
        Storage::fake('public');

        $file = $this->fakeImage();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->post('/api/me/profile-picture', [
            'profile_picture' => $file,
        ]);

        $response
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }
}
