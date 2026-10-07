<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePictureTest extends TestCase
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

        $token = 'profile-picture-session-token';

        Session::create([
            'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'ended_at' => null,
        ]);

        return [$account, $token];
    }

    public function test_user_can_update_profile_picture(): void
    {
        Storage::fake('public');

        [$account, $token] = $this->createAccountWithSession();

        $file = UploadedFile::fake()->image(
            'profile.jpg',
            200,
            200
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->post('/api/me/profile-picture', [
            'profile_picture' => $file,
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.account.profile_picture',
                $account->fresh()->profile_picture
            );

        $path = $account->fresh()->profile_picture;

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_user_can_replace_existing_profile_picture(): void
    {
        Storage::fake('public');

        [$account, $token] = $this->createAccountWithSession();

        $oldFile = UploadedFile::fake()->image(
            'old-profile.jpg',
            200,
            200
        );

        $oldPath = $oldFile->store(
            'profile-pictures/'.$account->id,
            'public'
        );

        $account->profile_picture = $oldPath;
        $account->save();

        Storage::disk('public')->assertExists($oldPath);

        $newFile = UploadedFile::fake()->image(
            'new-profile.jpg',
            300,
            300
        );

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->post('/api/me/profile-picture', [
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

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->post('/api/me/profile-picture', [
            'profile_picture' => 'not-an-image',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'profile_picture',
            ]);

        $this->assertNull($account->fresh()->profile_picture);
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

        $response = $this->withHeader(
            'Authorization',
            'Bearer '.$token
        )->post('/api/me/profile-picture', [
            'profile_picture' => $file,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'profile_picture',
            ]);

        $this->assertNull($account->fresh()->profile_picture);
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

        $response =
