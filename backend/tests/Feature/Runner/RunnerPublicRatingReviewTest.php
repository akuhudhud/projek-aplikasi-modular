<?php

namespace Tests\Feature\Runner;

use App\Http\Controllers\Api\RunnerPublicProfileController;
use App\Models\Account;
use App\Models\CoreTask;
use App\Models\RunnerPublicProfile;
use App\Models\RunnerTaskRating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunnerPublicRatingReviewTest extends TestCase
{
    use RefreshDatabase;

    private function createRunner(
        string $slug = 'runner-rating-test',
        string $status = 'ACTIVE',
        string $runnerStatus = Account::RUNNER_STATUS_APPROVED,
        bool $published = true
    ): Account {
        $account = Account::query()->create([
            'id' => (string) Str::uuid(),
            'profile_name' => 'Runner Rating Review Test',
            'phone' => null,
            'email' => null,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => $status,
        ]);

        $account->runner_status = $runnerStatus;
        $account->save();

        RunnerPublicProfile::query()->create([
            'profile_id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'public_slug' => $slug,
            'bio' => 'Profil ujian rating dan ulasan awam.',
            'base_location' => 'Tuaran',
            'service_area' => 'Tuaran - Kota Kinabalu sahaja',
            'services' => [],
            'is_published' => $published,
            'views_count' => 0,
        ]);

        return $account->fresh();
    }

    private function createRating(
        Account $runner,
        int $rating,
        ?string $review = null,
        ?string $customerId = null
    ): RunnerTaskRating {
        // Setiap rating mesti mempunyai pelanggan sebenar.
        $customer = $customerId !== null
            ? Account::query()->findOrFail($customerId)
            : Account::query()->create([
                'id' => (string) Str::uuid(),
                'profile_name' => 'Rating Test Customer',
                'phone' => null,
                'email' => null,
                'password' => 'Password1',
                'role' => 'USER',
                'status' => 'ACTIVE',
            ]);

        // Setiap rating mesti merujuk Core Task sebenar.
        $task = CoreTask::query()->create([
            'task_id' => (string) Str::uuid(),
            'module_key' => 'Marketplace',
            'task_type' => 'MARKETPLACE_SERVICE_REQUEST',
            'source_type' => 'rating_review_test',
            'source_id' => (string) Str::uuid(),
            'created_by_account_id' => $customer->id,
            'assigned_account_id' => $runner->id,
            'generic_status' => CoreTask::STATUS_COMPLETED,
            'assigned_at' => now(),
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        return RunnerTaskRating::query()->create([
            'rating_id' => (string) Str::uuid(),
            'task_id' => $task->task_id,
            'customer_account_id' => $customer->id,
            'runner_account_id' => $runner->id,
            'rating' => $rating,
            'review' => $review,
        ]);
    }

    public function test_public_profile_returns_average_and_rating_count(): void
    {
        $runner = $this->createRunner();

        $this->createRating($runner, 5, 'Penghantaran sangat baik.');
        $this->createRating($runner, 4);
        $this->createRating($runner, 3, 'Servis memuaskan.');

        $response = app(RunnerPublicProfileController::class)
            ->show('runner-rating-test');

        $this->assertSame(200, $response->getStatusCode());

        $profile = $response->getData(true)['data']['profile'];

        $this->assertEquals(4.0, $profile['rating_average']);
        $this->assertSame(3, $profile['rating_count']);
        $this->assertSame(
            'Runner Rating Review Test',
            $profile['profile_name']
        );

        $this->assertArrayNotHasKey('display_name', $profile);
        $this->assertArrayNotHasKey('verified_name', $profile);
        $this->assertArrayNotHasKey('review', $profile);
        $this->assertArrayNotHasKey('reviews', $profile);
        $this->assertArrayNotHasKey('customer_account_id', $profile);
        $this->assertArrayNotHasKey('runner_account_id', $profile);
    }

    public function test_public_profile_counts_one_as_the_minimum_valid_rating(): void
    {
        $runner = $this->createRunner();

        $this->createRating($runner, 1);
        $this->createRating($runner, 5);

        $response = app(RunnerPublicProfileController::class)
            ->show('runner-rating-test');

        $this->assertSame(200, $response->getStatusCode());

        $profile = $response->getData(true)['data']['profile'];

        $this->assertEquals(3.0, $profile['rating_average']);
        $this->assertSame(2, $profile['rating_count']);
    }

    public function test_public_profile_returns_null_average_when_no_ratings_exist(): void
    {
        $this->createRunner();

        $response = app(RunnerPublicProfileController::class)
            ->show('runner-rating-test');

        $this->assertSame(200, $response->getStatusCode());

        $profile = $response->getData(true)['data']['profile'];

        $this->assertNull($profile['rating_average']);
        $this->assertSame(0, $profile['rating_count']);
    }

    public function test_public_profile_rounds_average_to_one_decimal_place(): void
    {
        $runner = $this->createRunner();

        $this->createRating($runner, 5);
        $this->createRating($runner, 4);
        $this->createRating($runner, 4);

        $response = app(RunnerPublicProfileController::class)
            ->show('runner-rating-test');

        $this->assertSame(200, $response->getStatusCode());

        $profile = $response->getData(true)['data']['profile'];

        $this->assertSame(4.3, $profile['rating_average']);
        $this->assertSame(3, $profile['rating_count']);
    }

    public function test_unpublished_profile_does_not_expose_rating_summary(): void
    {
        $runner = $this->createRunner();

        $profile = RunnerPublicProfile::query()
            ->where('account_id', $runner->id)
            ->firstOrFail();

        $profile->is_published = false;
        $profile->save();

        $this->createRating($runner, 5);

        $response = app(RunnerPublicProfileController::class)
            ->show('runner-rating-test');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['success']);
    }

    public function test_public_reviews_hide_written_reviews_and_reviewer_identity(): void
    {
        $runner = $this->createRunner('runner-reviews-test');

        $this->createRating(
            $runner,
            5,
            'Penghantaran sangat baik.'
        );
        $this->createRating($runner, 4, null);
        $this->createRating($runner, 3, 'Servis memuaskan.');

        $response = $this->getJson(
            '/api/runners/runner-reviews-test/reviews'
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rating_summary.rating_average', 4.0)
            ->assertJsonPath('data.rating_summary.rating_count', 3)
            ->assertJsonPath('data.reviews', [])
            ->assertJsonPath('data.pagination.total', 0);

        $response->assertDontSee('Penghantaran sangat baik.');
        $response->assertDontSee('Servis memuaskan.');
        $response->assertJsonMissingPath(
            'data.reviews.0.customer_account_id'
        );
        $response->assertJsonMissingPath(
            'data.reviews.0.runner_account_id'
        );
    }

    public function test_public_reviews_return_empty_collection_when_no_ratings_exist(): void
    {
        $runner = $this->createRunner('runner-reviews-test');

        $response = $this->getJson(
            '/api/runners/runner-reviews-test/reviews'
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rating_summary.rating_average', null)
            ->assertJsonPath('data.rating_summary.rating_count', 0)
            ->assertJsonPath('data.reviews', [])
            ->assertJsonPath('data.pagination.total', 0);
    }

    public function test_unpublished_runner_profile_is_not_publicly_accessible(): void
    {
        $runner = $this->createRunner(
            slug: 'runner-reviews-test',
            published: false
        );

        $this->createRating(
            $runner,
            5,
            'Ulasan yang tidak boleh didedahkan.'
        );

        $this->getJson('/api/runners/runner-reviews-test/reviews')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_inactive_runner_reviews_are_not_publicly_accessible(): void
    {
        $this->createRunner(
            slug: 'runner-reviews-test',
            status: 'SUSPENDED'
        );

        $this->getJson('/api/runners/runner-reviews-test/reviews')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_unapproved_runner_reviews_are_not_publicly_accessible(): void
    {
        $this->createRunner(
            slug: 'runner-reviews-test',
            runnerStatus: Account::RUNNER_STATUS_NOT_RUNNER
        );

        $this->getJson('/api/runners/runner-reviews-test/reviews')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_public_reviews_never_expose_written_reviews_even_with_many_ratings(): void
    {
        $runner = $this->createRunner('runner-reviews-test');

        for ($index = 1; $index <= 21; $index++) {
            $this->createRating(
                $runner,
                5,
                'Ulasan sulit nombor '.$index
            );
        }

        $response = $this->getJson(
            '/api/runners/runner-reviews-test/reviews?page=2'
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.rating_summary.rating_average', 5.0)
            ->assertJsonPath('data.rating_summary.rating_count', 21)
            ->assertJsonPath('data.reviews', [])
            ->assertJsonPath('data.pagination.total', 0);

        $response->assertDontSee('Ulasan sulit nombor');
    }
}
