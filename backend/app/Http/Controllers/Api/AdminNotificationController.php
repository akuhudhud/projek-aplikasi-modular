<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $account = $request->attributes->get('account');

        if (!$this->isAdmin($account)) {
            return $this->forbidden();
        }

        $notifications = $account->notifications()
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $account->unreadNotifications()->count(),
                'notifications' => $notifications->items(),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                ],
            ],
        ]);
    }

    public function markAsRead(
        Request $request,
        string $notificationId
    ): JsonResponse {
        $account = $request->attributes->get('account');

        if (!$this->isAdmin($account)) {
            return $this->forbidden();
        }

        $notification = $account->notifications()
            ->where('id', $notificationId)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notifikasi tidak dijumpai.',
            ], 404);
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi ditandakan sebagai dibaca.',
            'data' => [
                'id' => $notification->id,
                'read_at' => $notification->fresh()->read_at,
                'unread_count' => $account->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $account = $request->attributes->get('account');

        if (!$this->isAdmin($account)) {
            return $this->forbidden();
        }

        $account->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Semua notifikasi telah ditandakan sebagai dibaca.',
            'data' => [
                'unread_count' => 0,
            ],
        ]);
    }

    private function isAdmin(mixed $account): bool
    {
        return $account instanceof Account
            && in_array(
                $account->role,
                ['ROOT_SUPER_ADMIN', 'SUPER_ADMIN'],
                true
            );
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Akses notifikasi Admin tidak dibenarkan.',
        ], 403);
    }
}
