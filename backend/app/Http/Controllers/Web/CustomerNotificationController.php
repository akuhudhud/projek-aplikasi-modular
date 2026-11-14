<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerNotificationController extends Controller
{
    private function getAccount(Request $request): ?Account
    {
        $account = $request->attributes->get('account');

        return $account instanceof Account ? $account : null;
    }

    /**
     * Senarai notifikasi pelanggan.
     */
    public function index(Request $request): JsonResponse
    {
        $account = $this->getAccount($request);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $notifications = $account->notifications()
            ->orderByDesc('created_at')
            ->orderBy('id')
            ->paginate(25);

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $notifications
                    ->getCollection()
                    ->map(fn ($notification) => [
                        'id' => $notification->id,
                        'type' => $notification->type,
                        'data' => $notification->data,
                        'read_at' => $notification->read_at,
                        'created_at' => $notification->created_at,
                    ])
                    ->values(),
                'summary' => [
                    'unread_total' => $account->notifications()
                        ->whereNull('read_at')
                        ->count(),
                ],
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'last_page' => $notifications->lastPage(),
                ],
            ],
        ], 200);
    }

    /**
     * Tandakan satu notifikasi sebagai dibaca.
     */
    public function markAsRead(
        Request $request,
        string $notificationId
    ): JsonResponse {
        $account = $this->getAccount($request);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $notification = $account->notifications()
            ->whereKey($notificationId)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data' => [
                'id' => $notification->id,
                'read_at' => $notification->fresh()->read_at,
            ],
        ], 200);
    }

    /**
     * Padam satu notifikasi.
     */
    public function destroy(
        Request $request,
        string $notificationId
    ): JsonResponse {
        $account = $this->getAccount($request);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $deleted = $account->notifications()
            ->whereKey($notificationId)
            ->delete();

        if ($deleted === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted.',
        ], 200);
    }

    /**
     * Padam semua notifikasi milik pelanggan.
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $account = $this->getAccount($request);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $deletedTotal = $account->notifications()->delete();

        return response()->json([
            'success' => true,
            'message' => 'All notifications deleted.',
            'data' => [
                'deleted_total' => $deletedTotal,
            ],
        ], 200);
    }
}
