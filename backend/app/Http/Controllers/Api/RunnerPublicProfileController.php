<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CoreTask;
use App\Models\MarketplaceQuotation;
use App\Models\MarketplaceRequest;
use App\Models\RunnerPublicProfile;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RunnerPublicProfileController extends Controller
{
    public function save(Request $request): JsonResponse
    {
        /** @var Account|null $account */
        $account = $request->attributes->get('account');

        if (!$account) {
            return $this->error('Unauthorized.', 401);
        }

        if (!$this->isEligibleRunner($account)) {
            return $this->error(
                'Only active and approved runners can manage a public profile.',
                403
            );
        }

        $profile = RunnerPublicProfile::query()
            ->where('account_id', $account->id)
            ->first();

        $validated = $request->validate([
            'public_slug' => [
                $profile ? 'sometimes' : 'required',
                'string',
                'alpha_dash',
                'min:3',
                'max:50',
            ],
            'display_name' => [
                $profile ? 'sometimes' : 'required',
                'string',
                'max:80',
            ],
            'bio' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'service_area' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
            ],
            'base_location' => [
                'sometimes',
                'nullable',
                'string',
                'max:120',
                function (
                    string $attribute,
                    mixed $value,
                    \Closure $fail
                ): void {
                    if (
                        $value !== null
                        && trim($value) !== ''
                        && !$this->isSafeAdministrativeAreaLabel($value)
                    ) {
                        $fail(
                            'Base location must contain an administrative-area label only. Do not enter coordinates, a postcode or a detailed street address.'
                        );
                    }
                },
            ],
            'coverage_area' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
            ],
            'services' => ['sometimes', 'nullable', 'array', 'max:20'],
            'services.*' => ['required', 'string', 'max:64'],
            'is_published' => ['sometimes', 'boolean'],
        ]);

        $slug = strtolower(trim(
            $validated['public_slug']
                ?? ($profile !== null ? $profile->public_slug : '')
        ));

        $slugTaken = RunnerPublicProfile::query()
            ->where('public_slug', $slug)
            ->where('account_id', '!=', $account->id)
            ->exists();

        if ($slugTaken) {
            return $this->error(
                'This public profile URL is already in use.',
                422
            );
        }

        $isPublished = (bool) (
            $validated['is_published']
                ?? ($profile !== null ? $profile->is_published : false)
        );

        $values = [
            'public_slug' => $slug,
            'display_name' => $profile !== null
                ? $profile->display_name
                : trim($validated['display_name']),
            'bio' => array_key_exists('bio', $validated)
                ? (
                    $validated['bio'] === null
                        ? null
                        : trim($validated['bio'])
                )
                : ($profile !== null ? $profile->bio : null),
            'service_area' => array_key_exists('service_area', $validated)
                ? (
                    $validated['service_area'] === null
                        ? null
                        : trim($validated['service_area'])
                )
                : ($profile !== null ? $profile->service_area : null),
            'base_location' => array_key_exists(
                'base_location',
                $validated
            )
                ? (
                    $validated['base_location'] === null
                        ? null
                        : trim($validated['base_location'])
                )
                : ($profile !== null ? $profile->base_location : null),
            'coverage_area' => array_key_exists(
                'coverage_area',
                $validated
            )
                ? (
                    $validated['coverage_area'] === null
                        ? null
                        : trim($validated['coverage_area'])
                )
                : ($profile !== null ? $profile->coverage_area : null),
            'services' => array_key_exists('services', $validated)
                ? ($validated['services'] ?? [])
                : (
                    $profile !== null
                        ? ($profile->services ?? [])
                        : []
                ),
            'is_published' => $isPublished,
        ];

        try {
            if ($profile !== null) {
                $profile->fill($values)->save();
            } else {
                $profile = RunnerPublicProfile::query()->create([
                    'profile_id' => (string) Str::uuid(),
                    'account_id' => $account->id,
                    ...$values,
                ]);
            }
        } catch (QueryException $exception) {
            if (
                RunnerPublicProfile::query()
                    ->where('public_slug', $slug)
                    ->where('account_id', '!=', $account->id)
                    ->exists()
            ) {
                return $this->error(
                    'This public profile URL is already in use.',
                    422
                );
            }

            throw $exception;
        }

        return response()->json([
            'success' => true,
            'message' => 'Runner public profile saved.',
            'data' => [
                'profile' => $this->privatePayload($profile->fresh()),
            ],
        ], $profile->wasRecentlyCreated ? 201 : 200);
    }

    public function show(string $slug): JsonResponse
    {
        $profile = RunnerPublicProfile::query()
            ->where('public_slug', strtolower($slug))
            ->where('is_published', true)
            ->first();

        if ($profile === null) {
            return $this->error('Runner profile not found.', 404);
        }

        $account = $profile->account;

        if ($account === null || !$this->isEligibleRunner($account)) {
            return $this->error('Runner profile not found.', 404);
        }

        $profile->increment('views_count');

        $publicBaseLocation = $this->isSafeAdministrativeAreaLabel(
            $profile->base_location
        )
            ? $profile->base_location
            : null;

        /*
         * Query Builder mengelakkan PHPStan menganggap alias
         * rating_count dan rating_average sebagai property model.
         */
        $ratingSummary = DB::table('runner_task_ratings')
            ->where('runner_account_id', $account->id)
            ->selectRaw(
                'COUNT(*) as rating_count, AVG(rating) as rating_average'
            )
            ->first();

        $ratingCount = (int) ($ratingSummary->rating_count ?? 0);

        $ratingAverage = $ratingCount > 0
            ? round((float) $ratingSummary->rating_average, 1)
            : null;

        $publicProfile = [
            'public_slug' => $profile->public_slug,
            'display_name' => $account->name,
            'bio' => $profile->bio,
            'service_area' => $profile->service_area,
            'base_location' => $publicBaseLocation,
            'coverage_area' => $profile->coverage_area,
            'services' => $profile->services ?? [],
            'rating_average' => $ratingAverage,
            'rating_count' => $ratingCount,
            'views_count' => $profile->views_count,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'profile' => $publicProfile,
            ],
        ]);
    }

    public function analytics(Request $request): JsonResponse
    {
        /** @var Account|null $account */
        $account = $request->attributes->get('account');

        if (!$account) {
            return $this->error('Unauthorized.', 401);
        }

        if (!$this->isEligibleRunner($account)) {
            return $this->error(
                'Only active and approved runners can view profile analytics.',
                403
            );
        }

        $profile = RunnerPublicProfile::query()
            ->where('account_id', $account->id)
            ->first();

        if ($profile === null) {
            return $this->error('Runner profile not found.', 404);
        }

        $profileRequests = MarketplaceRequest::query()
            ->where(
                'source_type',
                MarketplaceRequest::SOURCE_RUNNER_PROFILE_LINK
            )
            ->where('target_runner_account_id', $account->id);

        $requestCount = (clone $profileRequests)->count();

        $quotationCount = MarketplaceQuotation::query()
            ->whereIn(
                'request_id',
                (clone $profileRequests)->select('request_id')
            )
            ->count();

        $completedTaskCount = CoreTask::query()
            ->where('module_key', 'Marketplace')
            ->where('task_type', 'MARKETPLACE_SERVICE_REQUEST')
            ->where('source_type', 'marketplace_request')
            ->where('assigned_account_id', $account->id)
            ->where('generic_status', CoreTask::STATUS_COMPLETED)
            ->whereIn(
                'source_id',
                (clone $profileRequests)->select('request_id')
            )
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'analytics' => [
                    'profile_views' => (int) $profile->views_count,
                    'requests_from_profile' => $requestCount,
                    'quotations_for_profile_requests' => $quotationCount,
                    'completed_tasks_from_profile_requests' => $completedTaskCount,
                ],
            ],
        ]);
    }

    private function isSafeAdministrativeAreaLabel(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return true;
        }

        $location = trim($value);

        $looksLikeCoordinates = preg_match(
            '/^\s*[+-]?\d{1,3}(?:\.\d+)?\s*[,;\/]\s*[+-]?\d{1,3}(?:\.\d+)?\s*$/',
            $location
        ) === 1;

        $containsCoordinatePair = preg_match(
            '/(?<!\d)[+-]?\d{1,3}\.\d{3,}(?:\s*[,;\/]\s*|\s+)[+-]?\d{1,3}\.\d{3,}(?!\d)/',
            $location
        ) === 1;

        $containsPostcode = preg_match(
            '/(?<!\d)\d{5}(?!\d)/',
            $location
        ) === 1;

        $containsAddressMarker = preg_match(
            '/\b(?:jalan|jln|lorong|lrg|persiaran|lebuh|lebuh raya|highway|postcode|postal code|poskod|nombor|lot|unit|tingkat|blok|block|street address)\b/i',
            $location
        ) === 1;

        return !$looksLikeCoordinates
            && !$containsCoordinatePair
            && !$containsPostcode
            && !$containsAddressMarker;
    }

    private function isEligibleRunner(Account $account): bool
    {
        return $account->status === 'ACTIVE'
            && $account->runner_status === Account::RUNNER_STATUS_APPROVED;
    }

    private function privatePayload(RunnerPublicProfile $profile): array
    {
        return [
            'profile_id' => $profile->profile_id,
            'account_id' => $profile->account_id,
            'public_slug' => $profile->public_slug,
            'display_name' => $profile->account->name,
            'bio' => $profile->bio,
            'service_area' => $profile->service_area,
            'base_location' => $profile->base_location,
            'coverage_area' => $profile->coverage_area,
            'services' => $profile->services ?? [],
            'is_published' => $profile->is_published,
            'views_count' => $profile->views_count,
            'created_at' => $profile->created_at,
            'updated_at' => $profile->updated_at,
        ];
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
