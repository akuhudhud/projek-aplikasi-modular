<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentityVerificationSubmissionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_submit_identity_verification(): void
    {
        Storage::fake('local');

        $response = $this->post('/api/me/identity-verification', [
            'real_name' => 'Nama Ujian',
            'mykad_number' => '900101121234',
            'documents' => [
                UploadedFile::fake()->image('identity-document.jpg'),
            ],
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('identity_verifications', 0);
        $this->assertDatabaseCount('identity_verification_documents', 0);

        $this->assertSame(
            [],
            Storage::disk('local')->allFiles('identity-documents')
        );
    }
}
