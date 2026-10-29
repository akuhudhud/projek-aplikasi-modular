<?php

namespace Tests\Feature\Runner;

use App\Http\Controllers\Api\RunnerPublicProfileController;
use App\Models\Account;
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
            'name' => 'Runner Rating Review Test',
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
            'display_name' => 'Legacy Runner Name',
            'bio' => 'Profil ujian rating dan ulasan awam.',
            'service_area' => 'Tuaran',
            'base_location' => 'Tuaran, Sabah',
            'coverage_area' => 'Tuaran dan Tamparuli',
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
        return RunnerTaskRating::query()->create([
            'rating_id' => (string) Str::uuid(),
            'task_id' => (string) Str::uuid(),
            'customer_account_id' => $customerId
                ?? (string) Str::uuid(),
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
            $profile['display_name']
        );

        $this->assertArrayNotHasKey('review', $profile);
        $this->assertArrayNotHasKey('reviews', $profile);
        $this->assertArrayNotHasKey('customer_account_id', $profile);
        $this->assertArrayNotHasKey('runner_account_id', $profile);
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

    public function test_public_reviews_show_written_reviews_without_reviewer_identity(): void
    {
        $runner = $this->createRunner('runner-reviews-test');

        $this->createRating(
            $runner,
            5,
            'Penghantaran sangat baik.'
        );
        $this->createRating($runner, 4, null);
        $this->createRating($runner, 3, '   ');

        $response = $this->getJson(
            '/api/runners/runner-reviews-test/reviews'
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.reviews.0.rating', 5)
            ->assertJsonPath(
                'data.reviews.0.review',
                'Penghantaran sangat baik.'
            )
            ->assertJsonMissingPath(
                'data.reviews.0.customer_account_id'
            )
            ->assertJsonMissingPath(
                'data.reviews.0.runner_account_id'
            );

        $review = $response->json('data.reviews.0');

        $this->assertSame(
            ['rating', 'review', 'created_at'],
            array_keys($review)
        );
    }

    public function test_public_reviews_return_empty_collection_when_no_written_reviews_exist(): void
    {
        $runner = $this->createRunner('runner-reviews-test');

        $this->createRating($runner, 5, null);
        $this->createRating($runner, 4, '');

        $this->getJson('/api/runners/runner-reviews-test/reviews')
            ->assertOk()
            ->assertJsonPath('success', true)
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

    public function test_public_reviews_are_paginated_twenty_per_page(): void
    {
        $runner = $this->createRunner('runner-reviews-test');

        for ($index = 1; $index <= 21; $index++) {
            $this->createRating(
                $runner,
                5,
                'Ulasan nombor '.$index
            );
        }

        $firstPage = $this->getJson(
            '/api/runners/runner-reviews-test/reviews'
        );

        $firstPage
            ->assertOk()
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonPath('data.pagination.per_page', 20)
            ->assertJsonPath('data.pagination.last_page', 2)
            ->assertJsonPath('data.pagination.total', 21);

        $this->assertCount(20, $firstPage->json('data.reviews'));

        $secondPage = $this->getJson(
            '/api/runners/runner-reviews-test/reviews?page=2'
        );

        $secondPage
            ->assertOk()
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.pagination.total', 21);

        $this->assertCount(1, $secondPage->json('data.reviews'));
    }
}
