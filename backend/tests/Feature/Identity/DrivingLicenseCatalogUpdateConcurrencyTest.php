<?php

namespace Tests\Feature\Identity;

use App\Models\Account;
use App\Models\DrivingLicenseCatalog;
use App\Models\DrivingLicenseType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DrivingLicenseCatalogUpdateConcurrencyTest extends TestCase
{
    private array $workers = [];

    public function test_concurrent_catalog_updates_cannot_assign_same_class_code_under_same_type(): void
    {
        $this->requireMysqlConcurrency();

        $suffix = substr(
            strtolower(str_replace('-', '', (string) Str::uuid())),
            0,
            12
        );

        $actor = $this->makeAccount('catalog-update-'.$suffix);
        $type = $this->makeType('PARENT_'.$suffix);

        $first = $this->makeCatalog($type->id, 'FIRST_'.$suffix);
        $second = $this->makeCatalog($type->id, 'SECOND_'.$suffix);

        $newCode = 'SHARED_'.$suffix;

        try {
            $results = $this->runConcurrentUpdates(
                $actor->id,
                [$first->id, $second->id],
                $newCode
            );

            $successes = array_values(array_filter(
                $results,
                fn (array $result): bool =>
                    ($result['result'] ?? null) === 'SUCCESS'
                    && ($result['status'] ?? null) === 200
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

            $this->assertSame(
                1,
                DrivingLicenseCatalog::query()
                    ->where('driving_license_type_id', $type->id)
                    ->where('code', $newCode)
                    ->count()
            );

            $this->assertCount(1, $successes, json_encode($results));

            $this->assertCount(
                1,
                $rejections,
                'Expected the concurrent loser to receive HTTP 422. Results: '
                    .json_encode($results)
            );

            $this->assertCount(
                0,
                $exceptions,
                'No worker should throw an unhandled exception. Results: '
                    .json_encode($results)
            );

            $this->assertSame(
                'Kod kelas sudah wujud dalam jenis lesen ini.',
                $rejections[0]['body']['message'] ?? null,
                json_encode($results)
            );

            $this->assertSame(
                1,
                DB::table('audit_logs')
                    ->where('actor_account_id', $actor->id)
                    ->where('action', 'DRIVING_LICENSE_CATALOG_UPDATED')
                    ->count()
            );
        } finally {
            $this->stopWorkers();

            DB::table('audit_logs')
                ->where('actor_account_id', $actor->id)
                ->delete();

            DrivingLicenseCatalog::query()
                ->whereIn('id', [$first->id, $second->id])
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
                'Driving license catalog concurrency requires MySQL.'
            );
        }

        if (!function_exists('proc_open')) {
            $this->markTestSkipped(
                'Driving license catalog concurrency requires proc_open.'
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

    private function makeType(string $code): DrivingLicenseType
    {
        return DrivingLicenseType::query()->create([
            'id' => (string) Str::uuid(),
            'code' => $code,
            'name' => 'Concurrency '.$code,
            'description' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function makeCatalog(
        string $typeId,
        string $code
    ): DrivingLicenseCatalog {
        return DrivingLicenseCatalog::query()->create([
            'id' => (string) Str::uuid(),
            'driving_license_type_id' => $typeId,
            'code' => $code,
            'name' => 'Concurrency '.$code,
            'description' => null,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function runConcurrentUpdates(
        string $actorId,
        array $catalogIds,
        string $newCode
    ): array {
        $workerPath = __DIR__.'/../../Support/DrivingLicenseCatalogUpdateWorker.php';

        foreach ($catalogIds as $catalogId) {
            $pipes = [];

            $process = proc_open(
                [
                    PHP_BINARY,
                    $workerPath,
                    $actorId,
                    $catalogId,
                    $newCode,
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
                'Unable to start driving license catalog update worker.'
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
                'Catalog update worker failed: '.$errorOutput
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
