<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Guards the skeleton as the minimal internal (single-org) reference app:
 * no tenant plugins, no SaaS profile, no opt-in runtime config.
 */
final class InternalStackContractTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = dirname(__DIR__);
    }

    public function testAppProfileIsInternal(): void
    {
        $config = Yaml::parseFile($this->projectDir.'/config/packages/nubit_admin.yaml');

        self::assertSame('internal', $config['nubit_admin']['app_profile'] ?? null);
    }

    public function testRuntimeConfigIsDisabled(): void
    {
        $config = Yaml::parseFile($this->projectDir.'/config/packages/nubit_admin.yaml');

        self::assertFalse($config['nubit_admin']['runtime_config'] ?? false);
    }

    public function testBundlesExcludeTenantPlugins(): void
    {
        /** @var array<class-string, array<string, bool>> $bundles */
        $bundles = require $this->projectDir.'/config/bundles.php';

        foreach (array_keys($bundles) as $bundleClass) {
            self::assertStringNotContainsStringIgnoringCase('tenant', $bundleClass);
        }
    }

    public function testComposerDoesNotRequireTenantPackages(): void
    {
        /** @var array{require?: array<string, string>} $composer */
        $composer = json_decode(
            (string) file_get_contents($this->projectDir.'/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        foreach (array_keys($composer['require'] ?? []) as $package) {
            self::assertStringNotContainsStringIgnoringCase('tenant', $package);
        }
    }

    public function testEntitiesAreNotTenantScoped(): void
    {
        $entityDir = $this->projectDir.'/src/Entity';
        if (!is_dir($entityDir)) {
            self::markTestSkipped('No entities directory.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($entityDir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            self::assertStringNotContainsString('TenantScoped', $source, $file->getFilename());
            self::assertStringNotContainsString('tenant_id', $source, $file->getFilename());
        }
    }
}