<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace SBUERK\FixturePackages\Tests\Integration;

use Composer\Advisory\Auditor;
use Composer\Factory;
use Composer\Filter\PlatformRequirementFilter\PlatformRequirementFilterFactory;
use Composer\Installer;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Util\Filesystem;
use PHPUnit\Framework\TestCase;
use SBUERK\FixturePackages\Plugin\Config;

abstract class IntegrationTestCase extends TestCase
{
    protected Filesystem $composerFileSystem;
    private string $oldWorkingDirectory = '';

    private string $baseTestInstanceDirectory = __DIR__ . '/../../.cache/instances';

    private string $instanceTemplatePath = __DIR__ . '/Fixtures/Instances';

    protected function setUp(): void
    {
        $this->composerFileSystem = new Filesystem();
        $this->composerFileSystem->ensureDirectoryExists($this->baseTestInstancePath());

        // Backup current working folder, so it can be restored in self::tearDown(). Some tests requires to change
        // the working directory, but we should restore original working directory to avoid a dirty environment.
        $this->oldWorkingDirectory = getcwd();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Restore original working directory. Required for a couple of tests, which requires to operate
        // in fixture folders, but we want to be in starting folder afterward to avoid a dirty environment.
        chdir($this->oldWorkingDirectory);

        /**
         * Reset static property {@see Config::$instance} holding instance used within {@see Config::load()}. This
         * essential, otherwise tests will fail for different scenarios.
         */
        (new \ReflectionClass(Config::class))->setStaticPropertyValue('instance', null);

        parent::tearDown();
    }

    protected function copyInstanceTemplate(string $template, ?string $identifier = null): void
    {
        $identifier = $identifier ?: $this->identifier();
        $template = ltrim($this->composerFileSystem->normalizePath($template), '/');
        $identifier = ltrim($this->composerFileSystem->normalizePath($identifier), '/');
        $fullTemplatePath = $this->instanceTemplatePath() . '/' . $template;
        $fullTestInstancePath = $this->testInstancePath($identifier);
        if (!is_dir($fullTemplatePath)) {
            self::fail(sprintf('Template path "%s" does not exists, could not copy as test instance "%s".', $fullTemplatePath, $identifier));
        }
        $this->composerFileSystem->emptyDirectory($fullTestInstancePath);
        if (!$this->composerFileSystem->copy($fullTemplatePath, $fullTestInstancePath)) {
            self::fail(sprintf('Failed to copy template path "%s" to "%s".', $fullTemplatePath, $fullTestInstancePath));
        }
    }

    protected function testInstancePath(?string $identifier = null): string
    {
        return $this->baseTestInstancePath() . '/' . ($identifier ?: $this->identifier());
    }

    protected function instanceTemplatePath(): string
    {
        return $this->composerFileSystem->normalizePath($this->instanceTemplatePath);
    }

    protected function baseTestInstancePath(): string
    {
        return $this->composerFileSystem->normalizePath($this->baseTestInstanceDirectory);
    }

    protected function identifier(): string
    {
        return hash('sha256', \json_encode([
            'classFQN' => static::class,
            'name' => $this->getName(),
        ]));
    }

    protected function composerInstall(string $baseDir, ?IOInterface $io): int
    {
        chdir($baseDir);
        $io ??= new NullIO();
        $composerConfig = Factory::createConfig($io, $baseDir);
        $composer = Factory::create($io);
        $install = Installer::create($io, $composer);
        $composer->getInstallationManager()->setOutputProgress(false);
        $install
            ->setDryRun(false)
            ->setDownloadOnly(false)
            ->setVerbose(true)
            ->setPreferSource(true)
            ->setPreferDist(false)
            ->setDevMode(true)
            ->setDumpAutoloader(true)
            ->setOptimizeAutoloader(false)
            ->setClassMapAuthoritative(false)
            ->setApcuAutoloader(false, null)
            ->setPlatformRequirementFilter(PlatformRequirementFilterFactory::ignoreNothing())
            ->setAudit(false)
            ->setErrorOnAudit(false)
            ->setAuditFormat(Auditor::FORMAT_TABLE)
        ;
        return $install->run();
    }
}
