<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\AccountPermission;
use App\Models\IdentityVerification;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentityVerificationQueueApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(
        string $profileName,
        string $role = 'USER'
    ): Account {
        static $sequence = 0;
        $sequence++;

        return Account::create([
            'id' => (string) Str::uuid(),
            'profile_name' => $profileName,
            'phone' => '6012'.str_pad((string) $sequence, 7, '0', STR_PAD_LEFT),
            'email' => 'identity-queue-'.$sequence.'@example.test',
            'password' => 'Password1',
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    private function createReviewer(): Account
    {
        $reviewer = $this->createAccount(
            'Admin Queue Test',
            'SUPER_ADMIN'
        );

        AccountPermission::create([
            'account_id' => $reviewer->id,
            'permission_key' => 'identity_verifications.review',
        ]);

        return $reviewer;
    }

    private function authenticate(Account $account): static
    {
        $token = 'identity-queue-'.Str::uuid();

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

    private function createVerification(
        Account $account,
        string $status,
        string $verifiedName,
        int $minutesAgo = 0
    ): IdentityVerification {
        return IdentityVerification::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'verified_name' => $verifiedName,
            'mykad_number' => '900101145678',
            'status' => $status,
            'submitted_at' => now()->subMinutes($minutesAgo),
            'reviewed_at' => $status === IdentityVerification::STATUS_PENDING
                ? null
                : now(),
        ]);
    }

    public function test_queue_requires_review_permission(): void
    {
        $admin = $this->createAccount(
            'Admin Tanpa Permission',
            'SUPER_ADMIN'
        );

        $this->authenticate($admin)
            ->getJson('/api/admin/identity-verifications')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_queue_returns_pending_records_with_masked_mykad_and_pagination(): void
    {
        $reviewer = $this->createReviewer();

        for ($index = 0; $index < 16; $index++) {
            $account = $this->createAccount(
                'Pengguna Queue '.str_pad(
                    (string) $index,
                    2,
                    '0',
                    STR_PAD_LEFT
                )
            );

            $this->createVerification(
                $account,
                IdentityVerification::STATUS_PENDING,
                'Nama Disasar '.str_pad(
                    (string) $index,
                    2,
                    '0',
                    STR_PAD_LEFT
                ),
                $index
            );
        }

        $verifiedAccount = $this->createAccount(
            'Pengguna Telah Disahkan'
        );

        $this->createVerification(
            $verifiedAccount,
            IdentityVerification::STATUS_VERIFIED,
            'Nama Telah Disahkan',
            30
        );

        $response = $this->authenticate($reviewer)
            ->getJson(
                '/api/admin/identity-verifications?per_page=15&page=1'
            );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonPath('data.pagination.last_page', 2)
            ->assertJsonPath('data.pagination.per_page', 15)
            ->assertJsonPath('data.pagination.total', 16);

        $this->assertCount(
            15,
            $response->json('data.verifications')
        );

        $firstRecord = $response->json('data.verifications.0');

        $this->assertSame(
            '********5678',
            $firstRecord['mykad_number_masked']
        );

        $this->assertArrayNotHasKey(
            'mykad_number',
            $firstRecord
        );

        $this->assertArrayNotHasKey(
            'disk',
            $firstRecord
        );

        $this->assertArrayNotHasKey(
            'path',
            $firstRecord
        );

        $secondPage = $this->authenticate($reviewer)
            ->getJson(
                '/api/admin/identity-verifications?status=PENDING&per_page=15&page=2'
            );

        $secondPage->assertOk()
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.pagination.total', 16);

        $this->assertCount(
            1,
            $secondPage->json('data.verifications')
        );
    }

    public function test_queue_filters_by_status_and_search_term(): void
    {
        $reviewer = $this->createReviewer();

        $pendingAccount = $this->createAccount(
            'Profil Pengguna Sasaran'
        );

        $this->createVerification(
            $pendingAccount,
            IdentityVerification::STATUS_PENDING,
            'Nama Carian Khusus'
        );

        $verifiedAccount = $this->createAccount(
            'Profil Pengguna Disahkan'
        );

        $this->createVerification(
            $verifiedAccount,
            IdentityVerification::STATUS_VERIFIED,
            'Nama Pengguna Disahkan'
        );

        $searchResponse = $this->authenticate($reviewer)
            ->getJson(
                '/api/admin/identity-verifications'
                .'?status=PENDING&search=Nama%20Carian%20Khusus'
            );

        $searchResponse->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath(
                'data.verifications.0.verified_name',
                'Nama Carian Khusus'
            );

        $verifiedResponse = $this->authenticate($reviewer)
            ->getJson(
                '/api/admin/identity-verifications?status=VERIFIED'
            );

        $verifiedResponse->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath(
                'data.verifications.0.status',
                IdentityVerification::STATUS_VERIFIED
            );
    }
}
