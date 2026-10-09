<?php

namespace Tests\Feature\Modules;

use App\Providers\ModuleDiscoveryServiceProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Tests\TestCase;

class ModuleDiscoveryNegativeTest extends TestCase
{
    private array $fixtureDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->fixtureDirectories as $directory) {
            $this->removeFixtureDirectory($directory);
        }

        parent::tearDown();
    }

    public function test_disabled_module_provider_is_not_registered(): void
    {
        $this->assertProviderIsNotLoaded(
            DiscoveryFixtureServiceProvider::class
        );

        $this->createModuleFixture(
            'disabled',
            [
                'enabled' => false,
                'provider' => DiscoveryFixtureServiceProvider::class,
            ]
        );

        $this->runDiscovery();

        $this->assertProviderIsNotLoaded(
            DiscoveryFixtureServiceProvider::class
        );

        $this->assertSame(
            0,
            DiscoveryFixtureServiceProvider::$registrationCount
        );
    }

    public function test_non_array_module_metadata_is_ignored(): void
    {
        $this->createModuleFixture(
            'non_array',
            "'invalid metadata'"
        );

        $this->runDiscovery();

        $this->assertProviderIsNotLoaded(
            DiscoveryFixtureServiceProvider::class
        );
    }

    public function test_malformed_module_metadata_is_ignored(): void
    {
        $this->createModuleFixture(
            'malformed',
            '<?php this is invalid PHP syntax'
        );

        $this->runDiscovery();

        $this->assertProviderIsNotLoaded(
            DiscoveryFixtureServiceProvider::class
        );
    }

    public function test_nonexistent_provider_is_ignored(): void
    {
        $this->createModuleFixture(
            'missing_provider',
            [
                'enabled' => true,
                'provider' => 'Tests\\Feature\\Modules\\MissingDiscoveryProvider',
            ]
        );

        $this->runDiscovery();

        $this->assertProviderIsNotLoaded(
            DiscoveryFixtureServiceProvider::class
        );
    }

    public function test_class_that_is_not_a_service_provider_is_ignored(): void
    {
        $this->createModuleFixture(
            'invalid_provider_type',
            [
                'enabled' => true,
                'provider' => \stdClass::class,
            ]
        );

        $this->runDiscovery();

        $this->assertProviderIsNotLoaded(
            DiscoveryFixtureServiceProvider::class
        );
    }

    public function test_duplicate_provider_declarations_register_provider_once(): void
    {
        DiscoveryFixtureServiceProvider::$registrationCount = 0;

        $metadata = [
            'enabled' => true,
            'provider' => DiscoveryFixtureServiceProvider::class,
        ];

        $this->createModuleFixture('duplicate_a', $metadata);
        $this->createModuleFixture('duplicate_b', $metadata);

        $this->runDiscovery();

        $this->assertTrue(
            isset(
                $this->app->getLoadedProviders()[
                    DiscoveryFixtureServiceProvider::class
                ]
            ),
            'The valid provider must be registered.'
        );

        $this->assertSame(
            1,
            DiscoveryFixtureServiceProvider::$registrationCount,
            'A provider declared by multiple modules must register only once.'
        );
    }

    private function runDiscovery(): void
    {
        (new ModuleDiscoveryServiceProvider($this->app))->register();
    }

    private function assertProviderIsNotLoaded(string $providerClass): void
    {
        $this->assertFalse(
            isset($this->app->getLoadedProviders()[$providerClass]),
            "Provider {$providerClass} must not be registered."
        );
    }

    private function createModuleFixture(
        string $suffix,
        array|string $metadata
    ): string {
        $modulesDirectory = base_path('../modules');

        $directory = $modulesDirectory
            . '/__discovery_test_'
            . $suffix
            . '_'
            . Str::uuid()->toString();

        $configDirectory = $directory . '/Config';

        if (! mkdir($configDirectory, 0777, true) && ! is_dir($configDirectory)) {
            $this->fail("Could not create fixture directory: {$configDirectory}");
        }

        $this->fixtureDirectories[] = $directory;

        if (is_array($metadata)) {
            $contents = '<?php return '
                . var_export($metadata, true)
                . ';'
                . PHP_EOL;
        } elseif ($suffix === 'non_array') {
            $contents = '<?php return ' . $metadata . ';' . PHP_EOL;
        } else {
            $contents = $metadata . PHP_EOL;
        }

        $written = file_put_contents(
            $configDirectory . '/module.php',
            $contents
        );

        if ($written === false) {
            $this->fail("Could not write fixture metadata: {$configDirectory}/module.php");
        }

        return $directory;
    }

    private function removeFixtureDirectory(string $directory): void
    {
        $configFile = $directory . '/Config/module.php';
        $configDirectory = $directory . '/Config';

        if (is_file($configFile)) {
            unlink($configFile);
        }

        if (is_dir($configDirectory)) {
            rmdir($configDirectory);
        }

        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
}

/**
 * Test-only provider used to verify real module discovery behaviour.
 */
class DiscoveryFixtureServiceProvider extends ServiceProvider
{
    public static int $registrationCount = 0;

    public function register(): void
    {
        self::$registrationCount++;
    }
}
