<?php

namespace Tests\Feature\Runner;

use App\Http\Controllers\Api\RunnerPublicProfileController;
use App\Models\Account;
use App\Models\RunnerPublicProfile;
use App\Models\RunnerTaskRating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerPublicRatingSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function createPublishedRunnerProfile(): Account
    {
        $account = Account::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Runner Rating Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
        ]);

        $account->runner_status = Account::RUNNER_STATUS_APPROVED;
        $account->save();

        RunnerPublicProfile::query()->create([
            'profile_id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'public_slug' => 'runner-rating-test',
            'display_name' => 'Legacy Runner Name',
            'bio' => 'Profil ujian rating.',
            'service_area' => 'Tuaran',
            'base_location' => 'Tuaran, Sabah',
            'coverage_area' => 'Tuaran dan Tamparuli',
            'services' => [],
            'is_published' => true,
            'views_count' => 0,
        ]);

        return $account->fresh();
    }

    private function createRating(
        Account $runner,
        int $rating,
        ?string $review = null
    ): RunnerTaskRating {
        return RunnerTaskRating::query()->create([
            'rating_id' => (string) Str::uuid(),
            'task_id' => (string) Str::uuid(),
            'customer_account_id' => (string) Str::uuid(),
            'runner_account_id' => $runner->id,
            'rating' => $rating,
            'review' => $review,
        ]);
    }

    public function test_public_profile_returns_average_and_rating_count(): void
    {
        $runner = $this->createPublishedRunnerProfile();

        $this->createRating($runner, 5, 'Penghantaran sangat baik.');
        $this->createRating($runner, 4);
        $this->createRating($runner, 3, 'Servis memuaskan.');

        $response = app(RunnerPublicProfileController::class)
            ->show('runner-rating-test');

        $this->assertSame(200, $response->getStatusCode());

        $profile = $response->getData(true)['data']['profile'];

        // (5 + 4 + 3) / 3 = 4.0
        $this->assertSame(4.0, $profile['rating_average']);
        $this->assertSame(3, $profile['rating_count']);

        $this->assertSame(
            'Runner Rating Test',
            $profile['display_name']
        );

        $this->assertArrayNotHasKey('review', $profile);
        $this->assertArrayNotHasKey('reviews', $profile);
        $this->assertArrayNotHasKey('customer_account_id', $profile);
        $this->assertArrayNotHasKey('runner_account_id', $profile);
    }

    public function test_public_profile_returns_null_average_when_no_ratings_exist(): void
    {
        $this->createPublishedRunnerProfile();

        $response = app(RunnerPublicProfileController::class)
            ->show('runner-rating-test');

        $this->assertSame(200, $response->getStatusCode());

        $profile = $response->getData(true)['data']['profile'];

        $this->assertNull($profile['rating_average']);
        $this->assertSame(0, $profile['rating_count']);
    }

    public function test_public_profile_rounds_average_to_one_decimal_place(): void
    {
        $runner = $this->createPublishedRunnerProfile();

        $this->createRating($runner, 5);
        $this->createRating($runner, 4);
        $this->createRating($runner, 4);

        $response = app(RunnerPublicProfileController::class)
            ->show('runner-rating-test');

        $this->assertSame(200, $response->getStatusCode());

        $profile = $response->getData(true)['data']['profile'];

        // (5 + 4 + 4) / 3 = 4.333..., displayed as 4.3
        $this->assertSame(4.3, $profile['rating_average']);
        $this->assertSame(3, $profile['rating_count']);
    }

    public function test_unpublished_profile_does_not_expose_rating_summary(): void
    {
        $runner = $this->createPublishedRunnerProfile();

        $profile = RunnerPublicProfile::query()
            ->where('account_id', $runner->id)
            ->firstOrFail();

        $profile->is_published = false;
        $profile->save();

        $this->createRating($runner, 5);

        $response = app(RunnerPublicProfileController::class)
            ->show('runner-rating-test');

        $this->assertSame(404, $response->getStatusCode());

        $payload = $response->getData(true);

        $this->assertFalse($payload['success']);
    }
}
