<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\RunnerPublicProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RunnerPublicProfileController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $profile = RunnerPublicProfile::query()
            ->where('public_slug', $slug)
            ->where('is_published', true)
            ->first();

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profil runner tidak ditemui.',
            ], 404);
        }

        $account = Account::query()
            ->where('id', $profile->account_id)
            ->where('status', 'ACTIVE')
            ->where(
                'runner_status',
                Account::RUNNER_STATUS_APPROVED
            )
            ->first();

        if (! $account) {
            return response()->json([
                'success' => false,
                'message' => 'Profil runner tidak ditemui.',
            ], 404);
        }

        /*
         * Gunakan Query Builder untuk mengelakkan PHPStan
         * menganggap rating_count dan rating_average sebagai
         * property model RunnerTaskRating yang tidak wujud.
         */
        $ratingSummary = DB::table('runner_task_ratings')
            ->where('runner_account_id', $account->id)
            ->selectRaw(
                'COUNT(*) as rating_count, AVG(rating) as rating_average'
            )
            ->first();

        $ratingCount = (int) ($ratingSummary->rating_count ?? 0);

        $ratingAverage = $ratingCount > 0
            ? round(
                (float) $ratingSummary->rating_average,
                1
            )
            : null;

        $profileData = [
            'public_slug' => $profile->public_slug,
            'display_name' => $account->name,
            'bio' => $profile->bio,
            'service_area' => $profile->service_area,
            'coverage_area' => $profile->coverage_area,
            'services' => $profile->services,
            'rating_average' => $ratingAverage,
            'rating_count' => $ratingCount,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'profile' => $profileData,
            ],
        ]);
    }
}
