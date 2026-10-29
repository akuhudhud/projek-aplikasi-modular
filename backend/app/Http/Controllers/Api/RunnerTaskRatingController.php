<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CoreTask;
use App\Models\MarketplaceRequest;
use App\Models\RunnerTaskRating;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RunnerTaskRatingController extends Controller
{
    public function storeOrUpdate(
        Request $request,
        string $taskId
    ): JsonResponse {
        /** @var Account|null $account */
        $account = $request->attributes->get('account');

        if (!$account) {
            return $this->error('Unauthorized.', 401);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        try {
            [$rating, $created] = DB::transaction(
                function () use (
                    $account,
                    $taskId,
                    $validated
                ): array {
                    $task = CoreTask::query()
                        ->whereKey($taskId)
                        ->lockForUpdate()
                        ->first();

                    if (!$task) {
                        throw (new ModelNotFoundException())
                            ->setModel(CoreTask::class, [$taskId]);
                    }

                    if (
                        $task->module_key !== 'Marketplace'
                        || $task->task_type !== 'MARKETPLACE_SERVICE_REQUEST'
                        || $task->source_type !== 'marketplace_request'
                        || $task->generic_status !== CoreTask::STATUS_COMPLETED
                        || $task->assigned_account_id === null
                    ) {
                        throw new DomainException(
                            'Only completed Marketplace tasks can be rated.'
                        );
                    }

                    $marketplaceRequest = MarketplaceRequest::query()
                        ->whereKey($task->source_id)
                        ->first();

                    if (!$marketplaceRequest) {
                        throw (new ModelNotFoundException())
                            ->setModel(
                                MarketplaceRequest::class,
                                [$task->source_id]
                            );
                    }

                    if (
                        $marketplaceRequest->customer_account_id
                        !== $account->id
                    ) {
                        throw new AuthorizationException(
                            'Only the customer who created this task can rate it.'
                        );
                    }

                    $existing = RunnerTaskRating::query()
                        ->where('task_id', $task->task_id)
                        ->lockForUpdate()
                        ->first();

                    if ($existing !== null) {
                        if (
                            $existing->customer_account_id !== $account->id
                            || $existing->runner_account_id
                                !== $task->assigned_account_id
                        ) {
                            throw new DomainException(
                                'The existing rating does not match the task records.'
                            );
                        }

                        $existing->rating = (int) $validated['rating'];

                        // PATCH semantics: omitted review keeps its old value.
                        if (array_key_exists('review', $validated)) {
                            $existing->review = $validated['review'] === null
                                ? null
                                : trim($validated['review']);
                        }

                        $existing->save();

                        return [$existing->refresh(), false];
                    }

                    $values = [
                        'rating_id' => (string) Str::uuid(),
                        'task_id' => $task->task_id,
                        'customer_account_id' => $account->id,
                        'runner_account_id' => $task->assigned_account_id,
                        'rating' => (int) $validated['rating'],
                        'review' => array_key_exists('review', $validated)
                            && $validated['review'] !== null
                                ? trim($validated['review'])
                                : null,
                    ];

                    $rating = RunnerTaskRating::query()->create($values);

                    return [$rating, true];
                },
                3
            );
        } catch (ModelNotFoundException) {
            return $this->error('Task not found.', 404);
        } catch (AuthorizationException $exception) {
            return $this->error($exception->getMessage(), 403);
        } catch (DomainException $exception) {
            return $this->error($exception->getMessage(), 422);
        } catch (QueryException) {
            // Includes a concurrent attempt blocked by the unique task_id key.
            $existing = RunnerTaskRating::query()
                ->where('task_id', $taskId)
                ->first();

            if ($existing !== null) {
                return $this->error(
                    'Rating could not be saved because another request changed it. Retry the request.',
                    409
                );
            }

            throw new \RuntimeException(
                'Unable to save runner task rating.'
            );
        }

        return response()->json([
            'success' => true,
            'message' => $created
                ? 'Runner rating submitted.'
                : 'Runner rating updated.',
            'data' => [
                'rating' => [
                    'rating_id' => $rating->rating_id,
                    'task_id' => $rating->task_id,
                    'rating' => (int) $rating->rating,
                    'review' => $rating->review,
                    'created_at' => $rating->created_at,
                    'updated_at' => $rating->updated_at,
                ],
            ],
        ], $created ? 201 : 200);
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
