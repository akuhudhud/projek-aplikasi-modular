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

        $ratingCount = RunnerTaskRating::query()
            ->where('runner_account_id', $account->id)
            ->whereBetween('rating', [1, 5])
            ->count();

        $ratingAverage = RunnerTaskRating::query()
            ->where('runner_account_id', $account->id)
            ->whereBetween('rating', [1, 5])
            ->avg('rating');

        return response()->json([
            'success' => true,
            'data' => [
                'rating_summary' => [
                    'rating_average' => $ratingAverage !== null
                        ? round((float) $ratingAverage, 1)
                        : null,
                    'rating_count' => $ratingCount,
                ],
                'reviews' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 0,
                    'total' => 0,
                ],
            ],
        ], 200);
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
