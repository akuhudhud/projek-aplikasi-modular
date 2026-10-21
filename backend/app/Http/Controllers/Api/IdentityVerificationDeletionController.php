<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\Identity\IdentityVerificationDeletionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class IdentityVerificationDeletionController extends Controller
{
    public function destroy(
        Request $request,
        string $accountId,
        IdentityVerificationDeletionService $deletionService
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        /** @var Account $actor */
        $actor = $request->attributes->get('account');

        try {
            $deletionService->delete(
                $actor,
                $accountId,
                $validated['reason']
            );

            return response()->json([
                'success' => true,
                'message' => 'Data pengesahan identiti berjaya dipadam.',
            ], 200);
        } catch (ModelNotFoundException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 404);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 409);
        }
    }
}
