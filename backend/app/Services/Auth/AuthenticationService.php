<?php

namespace App\Services\Auth;

use App\Models\Account;
use App\Services\Session\SessionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthenticationService
{
    public function __construct(
        private SessionService $sessionService
    ) {
    }

    /**
     * Authenticate an account using phone or email and password.
     *
     * @return array{
     *     success: bool,
     *     status: int,
     *     message?: string,
     *     account?: Account,
     *     session?: array
     * }
     */
    public function authenticate(
        ?string $phone,
        ?string $email,
        string $password
    ): array {
        return DB::transaction(function () use (
            $phone,
            $email,
            $password
        ): array {
            $account = Account::query()
                ->when(
                    $phone !== null && $phone !== '',
                    fn ($query) => $query->where('phone', $phone)
                )
                ->when(
                    $email !== null && $email !== '',
                    fn ($query) => $query->where('email', $email)
                )
                ->lockForUpdate()
                ->first();

            if (!$account) {
                return $this->failure(
                    401,
                    'Invalid credentials.'
                );
            }

            if ($account->locked_until !== null) {
                if ($account->locked_until->isFuture()) {
                    return $this->failure(
                        423,
                        'Account is temporarily locked.'
                    );
                }

                $account->failed_login_attempts = 0;
                $account->locked_until = null;
                $account->save();
            }

            if (in_array(
                $account->status,
                ['DEACTIVATED', 'DELETED'],
                true
            )) {
                return $this->failure(
                    403,
                    'Account is not available for login.'
                );
            }

            if (!Hash::check($password, $account->password)) {
                $account->failed_login_attempts++;

                if ($account->failed_login_attempts >= 5) {
                    $account->locked_until = now()->addMinutes(30);
                }

                $account->save();

                if ($account->locked_until !== null) {
                    return $this->failure(
                        423,
                        'Account is temporarily locked.'
                    );
                }

                return $this->failure(
                    401,
                    'Invalid credentials.'
                );
            }

            $account->failed_login_attempts = 0;
            $account->locked_until = null;
            $account->save();

            $sessionData = $this->sessionService
                ->replaceActiveSessionsAndCreateLocked($account);

            if ($sessionData === null) {
                return $this->failure(
                    403,
                    'Account is not available for login.'
                );
            }

            return [
                'success' => true,
                'status' => 200,
                'account' => $account->fresh(),
                'session' => $sessionData,
            ];
        });
    }

    private function failure(
        int $status,
        string $message
    ): array {
        return [
            'success' => false,
            'status' => $status,
            'message' => $message,
        ];
    }
}
