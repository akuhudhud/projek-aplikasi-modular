<?php

namespace App\Services\Session;

use App\Models\Account;
use App\Models\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SessionService
{
    public const END_REASON_LOGOUT = 'LOGOUT';

    public const END_REASON_LOGIN_REPLACED = 'LOGIN_REPLACED';

    public const END_REASON_PASSWORD_CHANGED = 'PASSWORD_CHANGED';

    public const END_REASON_ACCOUNT_DEACTIVATED = 'ACCOUNT_DEACTIVATED';

    public const END_REASON_ACCOUNT_DELETED = 'ACCOUNT_DELETED';

    public const END_REASON_ACCOUNT_SUSPENDED = 'ACCOUNT_SUSPENDED';

    public const END_REASON_SECURITY_INVALIDATED = 'SECURITY_INVALIDATED';

    public function createForAccount(Account $account): array
    {
        $token = Str::random(64);

        $session = Session::create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'token_hash' => hash('sha256', $token),
            'created_at' => now(),
            'last_activity_at' => now(),
            'ended_at' => null,
            'end_reason' => null,
        ]);

        return [
            'session' => $session,
            'token' => $token,
        ];
    }

    public function replaceActiveSessionsAndCreate(
        Account $account
    ): array {
        return DB::transaction(function () use ($account) {
            $lockedAccount = Account::query()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->endAllForAccount(
                $lockedAccount,
                self::END_REASON_LOGIN_REPLACED
            );

            return $this->createForAccount($lockedAccount);
        });
    }

    public function end(
        Session $session,
        string $reason
    ): void {
        if ($session->ended_at !== null) {
            return;
        }

        $session->update([
            'ended_at' => now(),
            'end_reason' => $reason,
        ]);
    }

    public function endAllForAccount(
        Account $account,
        string $reason
    ): int {
        return Session::query()
            ->where('account_id', $account->id)
            ->whereNull('ended_at')
            ->update([
                'ended_at' => now(),
                'end_reason' => $reason,
            ]);
    }

    public function touch(Session $session): void
    {
        if ($session->ended_at !== null) {
            return;
        }

        $session->update([
            'last_activity_at' => now(),
        ]);
    }
}
