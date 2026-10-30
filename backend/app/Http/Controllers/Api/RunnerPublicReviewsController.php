<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\RunnerPublicProfile;
use App\Models\RunnerTaskRating;
use Illuminate\Http\JsonResponse;

class RunnerPublicReviewsController extends Controller
{
    public function index(string $slug): JsonResponse
    {
        $profile = RunnerPublicProfile::query()
            ->where('public_slug', strtolower($slug))
            ->where('is_published', true)
            ->first();

        if ($profile === null) {
            return $this->error('Runner profile not found.', 404);
        }

        $account = $profile->account;

        if (
            $account === null
            || $account->status !== 'ACTIVE'
            || $account->runner_status !== Account::RUNNER_STATUS_APPROVED
        ) {
            return $this->error('Runner profile not found.', 404);
        }

        $reviews = RunnerTaskRating::query()
            ->where('runner_account_id', $account->id)
            ->whereNotNull('review')
            ->whereRaw("TRIM(review) <> ''")
            ->orderByDesc('created_at')
            ->orderByDesc('rating_id')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'reviews' => $reviews->getCollection()
                    ->map(static fn (RunnerTaskRating $rating): array => [
                        'rating' => (int) $rating->rating,
                        'review' => $rating->review,
                        'created_at' => $rating->created_at,
                    ])
                    ->values(),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                ],
            ],
        ]);
    }

    private function error(
        string $message,
        int $status
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
