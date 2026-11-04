<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\DrivingLicenseCatalog;
use App\Models\DrivingLicenseType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DrivingLicenseCreationConcurrencyTest extends TestCase
{
    private array $workers = [];

    public function test_concurrent_creation_of_same_license_type_code_is_rejected_cleanly(): void
    {
        $this->requireMysqlConcurrency();

        $suffix = strtolower(str_replace('-', '', (string) Str::uuid()));
        $actor = $this->makeAccount('type-concurrency-'.$suffix);
        $code = 'TYPE_'.$suffix;

        try {
            $results = $this->runConcurrentWorkers(
                'type',
                $actor->id,
                null,
                $code
            );

            $this->assertOneSuccessOneRejection($results);

            $this->assertSame(
                1,
                DrivingLicenseType::query()
                    ->where('code', $code)
                    ->count()
            );

            $this->assertSame(
                1,
                DB::table('audit_logs')
                    ->where('actor_account_id', $actor->id)
                    ->where('action', 'DRIVING_LICENSE_TYPE_CREATED')
                    ->count()
            );
        } finally {
            $this->stopWorkers();

            DB::table('audit_logs')
                ->where('actor_account_id', $actor->id)
                ->delete();

            DrivingLicenseType::query()
                ->where('code', $code)
                ->delete();

            Account::query()->whereKey($actor->id)->delete();
        }
    }

    public function test_concurrent_creation_of_same_catalog_class_is_rejected_cleanly(): void
    {
        $this->requireMysqlConcurrency();

        $suffix = strtolower(str_replace('-', '', (string) Str::uuid()));
        $actor = $this->makeAccount('catalog-concurrency-'.$suffix);

        $type = DrivingLicenseType::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'PARENT_'.$suffix,
            'name' => 'Concurrency Parent '.$suffix,
            'description' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $code = 'CLASS_'.$suffix;

        try {
            $results = $this->runConcurrentWorkers(
                'catalog',
                $actor->id,
                $type->id,
                $code
            );

            $this->assertOneSuccessOneRejection($results);

            $this->assertSame(
                1,
                DrivingLicenseCatalog::query()
                    ->where('driving_license_type_id', $type->id)
                    ->where('code', $code)
                    ->count()
            );

            $this->assertSame(
                1,
                DB::table('audit_logs')
                    ->where('actor_account_id', $actor->id)
                    ->where('action', 'DRIVING_LICENSE_CATALOG_CREATED')
                    ->count()
            );
        } finally {
            $this->stopWorkers();

            DB::table('audit_logs')
                ->where('actor_account_id', $actor->id)
                ->delete();

            DrivingLicenseCatalog::query()
                ->where('driving_license_type_id', $type->id)
                ->where('code', $code)
                ->delete();

            DrivingLicenseType::query()
                ->whereKey($type->id)
                ->delete();

            Account::query()->whereKey($actor->id)->delete();
        }
    }

    private function requireMysqlConcurrency(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped(
                'Driving license concurrency requires MySQL row locking.'
            );
        }

        if (!function_exists('proc_open')) {
            $this->markTestSkipped(
                'Driving license concurrency requires proc_open.'
            );
        }
    }

    private function makeAccount(string $name): Account
    {
        $id = (string) Str::uuid();
        $now = now();

        DB::table('accounts')->insert([
            'id' => $id,
            'name' => $name,
            'password' => bcrypt('TestPassword123'),
            'role' => 'ROOT_SUPER_ADMIN',
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return Account::query()->findOrFail($id);
    }

    private function runConcurrentWorkers(
        string $mode,
        string $actorId,
        ?string $typeId,
        string $code
    ): array {
        $workerPath = __DIR__.'/../../Support/DrivingLicenseCreationWorker.php';

        for ($i = 0; $i < 2; $i++) {
            $pipes = [];

            $process = proc_open(
                [
                    PHP_BINARY,
                    $workerPath,
                    $mode,
                    $actorId,
                    $typeId ?? '',
                    $code,
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
                'Unable to start driving license concurrency worker.'
            );

            stream_set_blocking($pipes[1], true);
            stream_set_blocking($pipes[2], true);

            $this->workers[] = [
                'process' => $process,
                'pipes' => $pipes,
            ];
        }

        foreach ($this->workers as $worker) {
            $this->assertSame(
                "READY\n",
                fgets($worker['pipes'][1]),
                'A worker failed to reach the concurrency barrier.'
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
                'Worker failed: '.$errorOutput
            );

            $results[] = json_decode(
                trim($output),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }

        $this->workers = [];

        return $results;
    }

    private function assertOneSuccessOneRejection(array $results): void
    {
        $successes = array_values(array_filter(
            $results,
            fn (array $result): bool =>
                ($result['result'] ?? null) === 'SUCCESS'
                && ($result['status'] ?? null) === 201
        ));

        $rejections = array_values(array_filter(
            $results,
            fn (array $result): bool =>
                ($result['result'] ?? null) === 'REJECTED'
                && ($result['status'] ?? null) === 422
        ));

        $exceptions = array_values(array_filter(
            $results,
            fn (array $result): bool =>
                ($result['result'] ?? null) === 'EXCEPTION'
        ));

        $this->assertCount(0, $exceptions, json_encode($results));
        $this->assertCount(1, $successes, json_encode($results));
        $this->assertCount(1, $rejections, json_encode($results));
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
}
