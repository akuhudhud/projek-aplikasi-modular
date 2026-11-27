<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AdminPushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminPushSubscriptionController extends Controller
{
    public function publicKey(Request $request): JsonResponse
    {
        $account = $request->attributes->get('account');

        if (!$this->isActiveAdmin($account)) {
            return $this->forbidden();
        }

        $publicKey = config('services.webpush.public_key');

        if (!is_string($publicKey) || $publicKey === '') {
            return response()->json([
                'success' => false,
                'message' => 'Web Push belum dikonfigurasi.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'public_key' => $publicKey,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $account = $request->attributes->get('account');

        if (!$this->isActiveAdmin($account)) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'endpoint' => [
                'required',
                'string',
                'url',
                'max:4096',
            ],
            'keys.p256dh' => [
                'required',
                'string',
                'min:16',
                'max:200',
                'regex:/^[A-Za-z0-9_-]+$/',
            ],
            'keys.auth' => [
                'required',
                'string',
                'min:16',
                'max:200',
                'regex:/^[A-Za-z0-9_-]+$/',
            ],
            'content_encoding' => [
                'nullable',
                'string',
                'in:aes128gcm',
            ],
        ]);

        $endpoint = $validated['endpoint'];
        $endpointParts = parse_url($endpoint);

        if (
            !is_array($endpointParts)
            || ($endpointParts['scheme'] ?? null) !== 'https'
            || empty($endpointParts['host'])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Push endpoint mesti menggunakan HTTPS.',
            ], 422);
        }

        $endpointHash = hash('sha256', $endpoint);

        $subscription = DB::transaction(function () use (
            $account,
            $validated,
            $endpoint,
            $endpointHash
        ): AdminPushSubscription {
            $existing = AdminPushSubscription::query()
                ->where('endpoint_hash', $endpointHash)
                ->lockForUpdate()
                ->first();

            $values = [
                'account_id' => $account->id,
                'endpoint' => $endpoint,
                'endpoint_hash' => $endpointHash,
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['content_encoding']
                    ?? 'aes128gcm',
                'last_used_at' => now(),
            ];

            if ($existing) {
                $existing->update($values);

                return $existing->fresh();
            }

            return $account->pushSubscriptions()->create([
                'id' => (string) Str::uuid(),
                ...$values,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Peranti berjaya didaftarkan untuk notifikasi.',
            'data' => [
                'id' => $subscription->id,
            ],
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $account = $request->attributes->get('account');

        if (!$this->isActiveAdmin($account)) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'endpoint' => [
                'required',
                'string',
                'url',
                'max:4096',
            ],
        ]);

        $endpoint = $validated['endpoint'];
        $endpointParts = parse_url($endpoint);

        if (
            !is_array($endpointParts)
            || ($endpointParts['scheme'] ?? null) !== 'https'
            || empty($endpointParts['host'])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Push endpoint mesti menggunakan HTTPS.',
            ], 422);
        }

        $deleted = $account->pushSubscriptions()
            ->where(
                'endpoint_hash',
                hash('sha256', $endpoint)
            )
            ->delete();

        return response()->json([
            'success' => true,
            'message' => $deleted > 0
                ? 'Pendaftaran peranti berjaya dipadam.'
                : 'Pendaftaran peranti tidak dijumpai.',
        ]);
    }

    private function isActiveAdmin(mixed $account): bool
    {
        return $account instanceof Account
            && in_array(
                $account->role,
                ['ROOT_SUPER_ADMIN', 'SUPER_ADMIN'],
                true
            )
            && $account->status === 'ACTIVE';
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Akses pendaftaran notifikasi tidak dibenarkan.',
        ], 403);
    }
}
