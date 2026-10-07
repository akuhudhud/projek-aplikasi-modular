<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateProfilePictureRequest;
use App\Services\Profile\ProfilePictureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfilePictureController extends Controller
{
    public function update(
        UpdateProfilePictureRequest $request,
        ProfilePictureService $profilePictureService
    ): JsonResponse {
        $account = $request->attributes->get('account');

        $path = $profilePictureService->update(
            $account,
            $request->file('profile_picture')
        );

        return response()->json([
            'success' => true,
            'message' => 'Profile picture updated successfully.',
            'data' => [
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'phone' => $account->phone,
                    'email' => $account->email,
                    'profile_picture' => $path,
                    'role' => $account->role,
                    'status' => $account->status,
                ],
            ],
        ], 200);
    }
}
