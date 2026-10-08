<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConcurrentLoginTest extends TestCase
{
    private ?resource $workerProcess = null;

    private array $workerPipes = [];

    public function test_login_waits_for_account_row_lock_before_making_login_security_decision(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped(
                'The F-006 concurrency regression requires MySQL row locking.'
            );
        }

        if (! function_exists('proc_open')) {
            $this->markTestSkipped(
                'The F-006 concurrency regression requires proc_open.'
            );
        }

        $accountId = 'f0060000-0000-4000-8000-000000000001';
        $email = 'f006-concurrency@example.com';

        Account::query()
            ->whereKey($accountId)
            ->delete();

        Account::create([
            'id' => $accountId,
            'name' => 'F-006 Concurrency User',
            'email' => $email,
            'password' => 'Password1',
            'role' => 'USER',
            'status' => 'ACTIVE',
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $transactionStarted = false;

        try {
            DB::beginTransaction();
            $transactionStarted = true;

            Account::query()
                ->whereKey($accountId)
                ->lockForUpdate()
                ->firstOrFail();

            $workerPath = __DIR__
                . '/../../Support/ConcurrentLoginWorker.php';

            $this->startWorker(
                $workerPath,
                $email,
                'WrongPass1'
            );

            $ready = fgets($this->workerPipes[1]);

            $this->assertSame(
                "READY\n",
                $ready,
                'The concurrency worker did not reach the login request boundary.'
            );

            usleep(500000);

            $status = proc_get_status($this->workerProcess);

            $this->assertTrue(
                $status['running'],
                'The login request completed while the account row was locked. '
                . 'This indicates that the login-security transaction is not '
                . 'serializing the account decision with SELECT ... FOR UPDATE.'
            );

            DB::commit();
            $transactionStarted = false;

            $output = stream_get_contents($this->workerPipes[1]);

            $exitCode = proc_close($this->workerProcess);
            $this->workerProcess = null;

            $this->assertSame(
                0,
                $exitCode,
                'The concurrency worker exited unexpectedly.'
            );

            $result = json_decode(
                trim($output),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            $this->assertSame(401, $result['status']);
            $this->assertSame(
                'Invalid credentials.',
                $result['message']
            );

            $account = Account::query()
                ->whereKey($accountId)
                ->firstOrFail();

            $this->assertSame(
                1,
                $account->failed_login_attempts
            );

            $this->assertNull($account->locked_until);
        } finally {
            if ($transactionStarted) {
                DB::rollBack();
            }

            $this->stopWorker();

            Account::query()
                ->whereKey($accountId)
                ->delete();
        }
    }

    private function startWorker(
        string $workerPath,
        string $email,
        string $password
    ): void {
        $command = [
            PHP_BINARY,
            $workerPath,
            $email,
            $password,
        ];

        $this->workerProcess = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $this->workerPipes,
            dirname(__DIR__, 3)
        );

        $this->assertIsResource(
            $this->workerProcess,
            'Unable to start the concurrency worker process.'
        );

        stream_set_blocking($this->workerPipes[1], true);
        stream_set_blocking($this->workerPipes[2], true);
    }

    private function stopWorker(): void
    {
        if (! is_resource($this->workerProcess)) {
            return;
        }

        $status = proc_get_status($this->workerProcess);

        if ($status['running']) {
            proc_terminate($this->workerProcess);
        }

        foreach ($this->workerPipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        proc_close($this->workerProcess);

        $this->workerProcess = null;
        $this->workerPipes = [];
    }
}
