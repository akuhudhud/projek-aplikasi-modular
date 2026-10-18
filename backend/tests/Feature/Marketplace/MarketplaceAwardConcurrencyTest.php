<?php

namespace Tests\Feature\Marketplace;

use App\Models\Account;
use App\Models\CoreTask;
use App\Models\MarketplaceQuotation;
use App\Models\MarketplaceRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceAwardConcurrencyTest extends TestCase
{
    private array $workers = [];

    public function test_only_one_concurrent_award_wins_for_a_request(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped(
                'Marketplace award concurrency requires MySQL row locking.'
            );
        }

        if (!function_exists('proc_open')) {
            $this->markTestSkipped(
                'Marketplace award concurrency requires proc_open.'
            );
        }

        $suffix = strtolower(str_replace('-', '', (string) Str::uuid()));
        $customer = $this->makeAccount('customer-'.$suffix);
        $runnerOne = $this->makeAccount(
            'runner-one-'.$suffix,
            Account::RUNNER_STATUS_APPROVED
        );
        $runnerTwo = $this->makeAccount(
            'runner-two-'.$suffix,
            Account::RUNNER_STATUS_APPROVED
        );

        $requestId = (string) Str::uuid();
        $quotationOneId = (string) Str::uuid();
        $quotationTwoId = (string) Str::uuid();
        $now = now();

        MarketplaceRequest::query()->create([
            'request_id' => $requestId,
            'customer_account_id' => $customer->id,
            'source_type' => MarketplaceRequest::SOURCE_MARKETPLACE,
            'service_type' => 'GENERAL_SERVICE',
            'description' => 'Concurrent award integration test.',
            'status' => MarketplaceRequest::STATUS_OPEN,
        ]);

        MarketplaceQuotation::query()->create([
            'quotation_id' => $quotationOneId,
            'request_id' => $requestId,
            'runner_account_id' => $runnerOne->id,
            'amount' => '20.00',
            'currency' => 'MYR',
            'status' => MarketplaceQuotation::STATUS_SUBMITTED,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        MarketplaceQuotation::query()->create([
            'quotation_id' => $quotationTwoId,
            'request_id' => $requestId,
            'runner_account_id' => $runnerTwo->id,
            'amount' => '24.00',
            'currency' => 'MYR',
            'status' => MarketplaceQuotation::STATUS_SUBMITTED,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        try {
            $workerPath = __DIR__.'/../../Support/MarketplaceAwardWorker.php';

            $this->startWorker(
                $workerPath,
                $customer->id,
                $requestId,
                $quotationOneId
            );

            $this->startWorker(
                $workerPath,
                $customer->id,
                $requestId,
                $quotationTwoId
            );

            foreach ($this->workers as $worker) {
                $ready = fgets($worker['pipes'][1]);

                $this->assertSame(
                    "READY\n",
                    $ready,
                    'An award worker failed to reach the concurrency barrier.'
                );
            }

            foreach ($this->workers as $worker) {
                fwrite($worker['pipes'][0], "GO\n");
                fflush($worker['pipes'][0]);
                fclose($worker['pipes'][0]);
            }

            $results = [];

            foreach ($this->workers as $index => $worker) {
                $output = stream_get_contents($worker['pipes'][1]);
                $errorOutput = stream_get_contents($worker['pipes'][2]);

                fclose($worker['pipes'][1]);
                fclose($worker['pipes'][2]);

                $exitCode = proc_close($worker['process']);
                $this->workers[$index]['process'] = null;

                $this->assertSame(
                    0,
                    $exitCode,
                    'Award worker failed: '.$errorOutput
                );

                $results[] = json_decode(
                    trim($output),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            }

            $successes = array_values(array_filter(
                $results,
                fn (array $result): bool =>
                    ($result['result'] ?? null) === 'SUCCESS'
            ));

            $rejections = array_values(array_filter(
                $results,
                fn (array $result): bool =>
                    ($result['result'] ?? null) === 'REJECTED'
            ));

            $this->assertCount(1, $successes, json_encode($results));
            $this->assertCount(1, $rejections, json_encode($results));

            $request = MarketplaceRequest::query()
                ->findOrFail($requestId);

            $this->assertSame(
                MarketplaceRequest::STATUS_AWARDED,
                $request->status
            );

            $this->assertContains(
                $request->winning_quotation_id,
                [$quotationOneId, $quotationTwoId]
            );

            $this->assertDatabaseCount('core_tasks', 1);

            $this->assertSame(
                1,
                CoreTask::query()
                    ->where('module_key', 'Marketplace')
                    ->where('task_type', 'MARKETPLACE_SERVICE_REQUEST')
                    ->where('source_id', $requestId)
                    ->count()
            );

            $this->assertSame(
                1,
                MarketplaceQuotation::query()
                    ->where('request_id', $requestId)
                    ->where(
                        'status',
                        MarketplaceQuotation::STATUS_ACCEPTED
                    )
                    ->count()
            );

            $this->assertSame(
                1,
                MarketplaceQuotation::query()
                    ->where('request_id', $requestId)
                    ->where(
                        'status',
                        MarketplaceQuotation::STATUS_NOT_SELECTED
                    )
                    ->count()
            );
        } finally {
            $this->stopWorkers();

            CoreTask::query()
                ->where('module_key', 'Marketplace')
                ->where('task_type', 'MARKETPLACE_SERVICE_REQUEST')
                ->where('source_id', $requestId)
                ->delete();

            MarketplaceQuotation::query()
                ->where('request_id', $requestId)
                ->delete();

            MarketplaceRequest::query()
                ->whereKey($requestId)
                ->delete();

            Account::query()
                ->whereIn('id', [
                    $customer->id,
                    $runnerOne->id,
                    $runnerTwo->id,
                ])
                ->delete();
        }
    }

    private function startWorker(
        string $workerPath,
        string $customerId,
        string $requestId,
        string $quotationId
    ): void {
        $pipes = [];

        $process = proc_open(
            [
                PHP_BINARY,
                $workerPath,
                $customerId,
                $requestId,
                $quotationId,
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            dirname(__DIR__, 3)
        );

        $this->assertIsResource(
            $process,
            'Unable to start Marketplace award worker.'
        );

        stream_set_blocking($pipes[1], true);
        stream_set_blocking($pipes[2], true);

        $this->workers[] = [
            'process' => $process,
            'pipes' => $pipes,
        ];
    }

    private function stopWorkers(): void
    {
        foreach ($this->workers as $worker) {
            if (!is_resource($worker['process'])) {
                continue;
            }

            $status = proc_get_status($worker['process']);

            if ($status['running']) {
                proc_terminate($worker['process']);
            }

            foreach ($worker['pipes'] as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            proc_close($worker['process']);
        }

        $this->workers = [];
    }

    private function makeAccount(
        string $name,
        string $runnerStatus = Account::RUNNER_STATUS_NOT_RUNNER
    ): Account {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => $name,
            'password' => bcrypt('TestPassword123'),
            'role' => 'USER',
            'status' => 'ACTIVE',
            'runner_status' => $runnerStatus,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return Account::query()->findOrFail($id);
    }
}
