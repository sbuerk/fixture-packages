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

namespace SBUERK\FixturePackages\Tests\Unit\Plugin;

use Composer\Advisory\Auditor;
use Composer\Composer;
use Composer\Factory;
use Composer\Filter\PlatformRequirementFilter\PlatformRequirementFilterFactory;
use Composer\Installer;
use Composer\IO\BufferIO;
use Composer\Package\RootPackage;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Util\Filesystem;
use SBUERK\FixturePackages\Composer\Repository\FixturePathRepository;
use SBUERK\FixturePackages\Plugin\Config;
use SBUERK\FixturePackages\Plugin\FixturePackagesService;
use SBUERK\FixturePackages\Tests\Unit\BaseUnitTestCase;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * @covers \SBUERK\FixturePackages\Plugin\FixturePackagesService
 */
final class FixturePackagesServiceTest extends BaseUnitTestCase
{
    /**
     * @return string[]
     */
    private function getRepositoryPackageNames(RepositoryInterface $repository): array
    {
        $names = [];
        foreach ($repository->getPackages() as $package) {
            self::assertSame('fixture-path', $package->getDistType());
            $names[] = $package->getName();
        }
        return $names;
    }

    /**
     * @return string[]
     */
    private function getRepositoryManagerPackageNames(RepositoryManager $repositoryManager): array
    {
        $names = [];
        foreach ($repositoryManager->getRepositories() as $repository) {
            self::assertInstanceOf(FixturePathRepository::class, $repository);
            foreach ($repository->getPackages() as $package) {
                self::assertSame('fixture-path', $package->getDistType());
                $names[] = $package->getName();
            }
        }
        return $names;
    }

    public static function createRepositoryForPathDataSets(): \Generator
    {
        $baseDir = (new Filesystem())->normalizePath(__DIR__ . '/../../Fixtures/fixture-path-repository-structure');
        yield 'one subfolder level works for wildcard path' => [
            'extra' => [
                'sbuerk/fixture-packages' => [
                    'paths' => [
                        'Fixtures/Extensions/*' => ['autoload'],
                    ],
                ],
            ],
            'baseDir' => $baseDir,
            'path' => 'Fixtures/Extensions/*',
            'expectedPackageNames' => [
                'vendor/test-extension-one',
                'vendor/test-extension-two',
            ],
        ];
        yield 'two subfolder level works for two wildcard path' => [
            'extra' => [
                'sbuerk/fixture-packages' => [
                    'paths' => [
                        'Fixtures/*/*' => ['autoload'],
                    ],
                ],
            ],
            'baseDir' => $baseDir,
            'path' => 'Fixtures/*/*',
            'expectedPackageNames' => [
                'vendor/test-extension-one',
                'vendor/test-extension-two',
                'vendor/test-extension-three',
                'vendor/test-package-two',
                'vendor/test-package-one',
            ],
        ];
        yield 'multi path wildcards works' => [
            'extra' => [
                'sbuerk/fixture-packages' => [
                    'paths' => [
                        'Packages/*/Fixtures/Extensions/*' => ['autoload'],
                    ],
                ],
            ],
            'baseDir' => $baseDir,
            'path' => 'Packages/*/Fixtures/Extensions/*',
            'expectedPackageNames' => [
                'vendor/test-extension-four',
                'vendor/test-extension-five',
            ],
        ];
        yield 'Package path without wildcard works' => [
            'extra' => [
                'sbuerk/fixture-packages' => [
                    'paths' => [
                        'Fixtures/Extensions/extension-one' => ['autoload'],
                    ],
                ],
            ],
            'baseDir' => $baseDir,
            'path' => 'Fixtures/Extensions/extension-one',
            'expectedPackageNames' => [
                'vendor/test-extension-one',
            ],
        ];
    }

    /**
     * @dataProvider createRepositoryForPathDataSets
     * @test
     */
    public function createRepositoryForPathReturnsRepositoryWithExpectedPackageNames(
        array $extra,
        string $baseDir,
        string $path,
        array $expectedPackageNames
    ): void {
        chdir($baseDir);
        $composerConfig = new \Composer\Config(false, $baseDir);
        $rootPackage = new RootPackage('fake/root', '1.0.0', '1.0.0.0');
        $rootPackage->setRepositories([
            // Disable packagist to avoid network/internet calls
            'packagist' => false,
        ]);
        $rootPackage->setExtra($extra);
        $composer = new Composer();
        $composer->setConfig($composerConfig);
        $composer->setPackage($rootPackage);
        $subject = new FixturePackagesService();
        $bufferedIo = new BufferIO();
        $invokableCreateRepositoryManager = $this->createClassMethodInvoker($subject, 'createRepositoryManager');
        $invokableCreateRepositoryForPath = $this->createClassMethodInvoker($subject, 'createRepositoryForPath');
        $repositoryManager = $invokableCreateRepositoryManager->invoke($subject, $bufferedIo, $composer);
        $repository = $invokableCreateRepositoryForPath->invoke($subject, $repositoryManager, $composer, $bufferedIo, $path, ['autoload']);
        self::assertInstanceOf(RepositoryInterface::class, $repository);
        self::assertInstanceOf(FixturePathRepository::class, $repository);
        self::assertSame($expectedPackageNames, $this->getRepositoryPackageNames($repository));
    }

    /**
     * @dataProvider createRepositoryForPathDataSets
     * @test
     */
    public function createPreparedRepositoryManagerReturnsRepositoryManagerWithExpectedRepositoriesAndPackages(
        array $extra,
        string $baseDir,
        string $path,
        array $expectedPackageNames
    ): void {
        chdir($baseDir);
        $bufferedIo = new BufferIO();
        $composerConfig = new \Composer\Config(false, $baseDir);
        $rootPackage = new RootPackage('fake/root', '1.0.0', '1.0.0.0');
        $rootPackage->setExtra($extra);
        $rootPackage->setRepositories([
            // Disable packagist to avoid network/internet calls
            'packagist.org' => false,
        ]);
        $composer = new Composer();
        $composer->setConfig($composerConfig);
        $composer->setPackage($rootPackage);
        $config = Config::load($composer, $bufferedIo);
        $subject = new FixturePackagesService();
        $invokablePrepareRepositoryManager = $this->createClassMethodInvoker($subject, 'createPreparedRepositoryManager');
        $repositoryManager = $invokablePrepareRepositoryManager->invoke($subject, $config, $composer, $bufferedIo);
        $packageNames = $this->getRepositoryManagerPackageNames($repositoryManager);
        self::assertSame($expectedPackageNames, $packageNames);
    }

    public static function createExportPackagesArrayDataSets(): \Generator
    {
        $baseDir = (new Filesystem())->normalizePath(__DIR__ . '/../../Fixtures/fixture-path-repository-structure');
        yield 'exported data has expected structure' => [
            'extra' => [
                'sbuerk/fixture-packages' => [
                    'paths' => [
                        'Fixtures/Extensions/*',
                    ],
                ],
            ],
            'baseDir' => $baseDir,
            'expectedExportArray' => [
                'vendor/test-extension-one' => [
                    'name' => 'vendor/test-extension-one',
                    'type' => 'typo3-cms-extension',
                    'path' => '../../Fixtures/Extensions/extension-one',
                    'extra' => [
                        'typo3/cms' => [
                            'extension-key' => 'extension_one',
                        ],
                    ],
                ],
                'vendor/test-extension-two' => [
                    'name' => 'vendor/test-extension-two',
                    'type' => 'typo3-cms-extension',
                    'path' => '../../Fixtures/Extensions/extension-two',
                    'extra' => [
                        'typo3/cms' => [
                            'extension-key' => 'extension_two',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider createExportPackagesArrayDataSets
     * @test
     */
    public function createExportPackagesArrayReturnsExpectedArray(
        array $extra,
        string $baseDir,
        array $expectedExportArray
    ): void {
        chdir($baseDir);
        $bufferedIo = new BufferIO();
        $composerConfig = new \Composer\Config(false, $baseDir);
        $rootPackage = new RootPackage('fake/root', '1.0.0', '1.0.0.0');
        $rootPackage->setExtra($extra);
        $rootPackage->setRepositories([
            // Disable packagist to avoid network/internet calls
            'packagist.org' => false,
        ]);
        $composer = new Composer();
        $composer->setConfig($composerConfig);
        $composer->setPackage($rootPackage);
        $config = Config::load($composer, $bufferedIo);
        $subject = new FixturePackagesService();
        $invokableGetTestFixturePackages = $this->createClassMethodInvoker($subject, 'getTestFixturePackages');
        $packages = array_values($invokableGetTestFixturePackages->invoke($subject, $config, $composer, $bufferedIo, []));
        $invokableCreateExportPackagesArray = $this->createClassMethodInvoker($subject, 'createExportPackagesArray');
        self::assertSame($expectedExportArray, $invokableCreateExportPackagesArray->invoke($subject, $config, $baseDir . '/vendor/sbuerk', ...$packages));
    }

    /**
     * Test with composer install example - not used yet.
     *
     * @todo Create composer install tests as e2e tests.
     * @todo Handle stdErr (PHP warnings not possible to read files due to missing exist checks in composer).
     */
    private function composerInstallExample(): void
    {
        // @todo Implement a better recursive cleanup/recursive folder removal code. Should be part of tearDown().
        `rm -rf tests/Fixtures/fixture-path-repository-structure/vendor tests/Fixtures/fixture-path-repository-structure/composer.lock`;
        $baseDir = realpath(__DIR__ . '/../../Fixtures/fixture-path-repository-structure');
        chdir($baseDir);
        $bufferedIO = new BufferIO('', StreamOutput::VERBOSITY_DEBUG);
        $composerConfig = Factory::createConfig($bufferedIO, $baseDir);
        $composer = Factory::create($bufferedIO);
        $install = Installer::create($bufferedIO, $composer);
        $composer->getInstallationManager()->setOutputProgress(false);
        $rootPackage = $composer->getPackage();
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
        $install->run();
        $output = $bufferedIO->getOutput();
        $b = 1;
    }
}
