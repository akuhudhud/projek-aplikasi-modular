<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AdminPushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class AdminWebPushService
{
    /**
     * Send a Web Push notification to all subscriptions
     * belonging to the specified Admin account.
     *
     * @return array{
     *     sent: int,
     *     failed: int,
     *     expired: int,
     *     skipped: bool
     * }
     */
    public function sendToAccount(
        Account $account,
        array $payload
    ): array {
        $result = [
            'sent' => 0,
            'failed' => 0,
            'expired' => 0,
            'skipped' => false,
        ];

        $subject = config('services.webpush.subject');
        $publicKey = config('services.webpush.public_key');
        $privateKey = config('services.webpush.private_key');

        if (
            !is_string($subject) ||
            $subject === '' ||
            !is_string($publicKey) ||
            $publicKey === '' ||
            !is_string($privateKey) ||
            $privateKey === ''
        ) {
            Log::warning(
                'Admin Web Push skipped: VAPID configuration is incomplete.'
            );

            $result['skipped'] = true;

            return $result;
        }

        $subscriptions = $account
            ->pushSubscriptions()
            ->get();

        if ($subscriptions->isEmpty()) {
            return $result;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => $subject,
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ]);

            $webPush->setDefaultOptions([
                'TTL' => 3600,
                'urgency' => 'high',
            ]);

            $message = json_encode(
                $payload,
                JSON_THROW_ON_ERROR
            );

            foreach ($subscriptions as $storedSubscription) {
                try {
                    $subscription = Subscription::create([
                        'endpoint' => $storedSubscription->endpoint,
                        'keys' => [
                            'p256dh' => $storedSubscription->public_key,
                            'auth' => $storedSubscription->auth_token,
                        ],
                        'contentEncoding' =>
                            $storedSubscription->content_encoding,
                    ]);

                    $report = $webPush->sendOneNotification(
                        $subscription,
                        $message
                    );

                    if ($report->isSuccess()) {
                        $storedSubscription->forceFill([
                            'last_used_at' => now(),
                        ])->save();

                        $result['sent']++;

                        continue;
                    }

                    if ($report->isSubscriptionExpired()) {
                        $storedSubscription->delete();

                        $result['expired']++;

                        continue;
                    }

                    $result['failed']++;

                    Log::warning(
                        'Admin Web Push delivery failed.',
                        [
                            'subscription_id' => $storedSubscription->id,
                            'reason' => $report->getReason(),
                        ]
                    );
                } catch (Throwable $exception) {
                    $result['failed']++;

                    Log::error(
                        'Admin Web Push subscription error.',
                        [
                            'subscription_id' => $storedSubscription->id,
                            'exception' => $exception->getMessage(),
                        ]
                    );
                }
            }
        } catch (Throwable $exception) {
            Log::error(
                'Admin Web Push service failed.',
                [
                    'account_id' => $account->id,
                    'exception' => $exception->getMessage(),
                ]
            );

            $result['failed'] += $subscriptions->count();
        }

        return $result;
    }
}
