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

use Composer\Composer;
use Composer\IO\BufferIO;
use Composer\Package\RootPackage;
use SBUERK\FixturePackages\Plugin\Config;
use SBUERK\FixturePackages\Tests\Unit\BaseUnitTestCase;

/**
 * @covers \SBUERK\FixturePackages\Plugin\Config
 */
final class ConfigTest extends BaseUnitTestCase
{
    public static function normalizePathDataSets(): \Generator
    {
        yield 'Empty string returns empty string' => [
            'value' => '',
            'expectedValue' => '',
        ];
        yield 'Single slash is returned as single slash' => [
            'value' => '/',
            'expectedValue' => '/',
        ];
        yield 'Drive letter is kept' => [
            'value' => 'C:/',
            'expectedValue' => 'C:',
        ];
        yield 'Windows path backslash is normalized to slash keeping drive letter' => [
            'value' => 'C:\\',
            'expectedValue' => 'C:',
        ];
        yield 'Unix path are trimmed' => [
            'value' => '/some/path/',
            'expectedValue' => '/some/path',
        ];
        yield 'Unix path ending in backslash is trimmed' => [
            'value' => '/some/path\\',
            'expectedValue' => '/some/path',
        ];
        yield 'Windows path are trimmed keeping drive letter' => [
            'value' => 'C:/some/path/',
            'expectedValue' => 'C:/some/path',
        ];
        yield 'Windows path ending in backslash are trimmed keeping drive letter' => [
            'value' => 'C:/some/path\\',
            'expectedValue' => 'C:/some/path',
        ];
    }

    /**
     * @dataProvider normalizePathDataSets
     * @test
     */
    public function normalizePathReturnsExpectedValue(string $value, string $expectedValue): void
    {
        $realpathMethodInvoker = $this->createClassMethodInvoker(Config::class, 'normalizePath');
        self::assertSame($expectedValue, $realpathMethodInvoker->invoke(new Config('/fake/root'), $value));
    }

    public static function realpathDataSets(): \Generator
    {
        yield 'Empty path returns baseDir' => [
            'baseDir' => '/fake/root',
            'path' => '',
            'expectedPath' => '/fake/root',
        ];
        yield 'Valid absolute path is returned directly' => [
            'baseDir' => '/fake/root',
            'path' => '/some/absolute/path',
            'expectedPath' => '/some/absolute/path',
        ];
        yield 'Valid absolute path is returned trimmed' => [
            'baseDir' => '/fake/root',
            'path' => '/some/absolute/path/',
            'expectedPath' => '/some/absolute/path',
        ];
        yield 'Mixed slash and backslash absolute path is returned normalized to slashes' => [
            'baseDir' => '/fake/root',
            'path' => '/some\absolute/path/',
            'expectedPath' => '/some/absolute/path',
        ];
        yield 'Valid normalized absolute windows path is returned directly keeping drive letter' => [
            'baseDir' => '/fake/root',
            'path' => 'C:/some/absolute/path',
            'expectedPath' => 'C:/some/absolute/path',
        ];
        yield 'Valid absolute windows path is returned normalized keeping drive letter' => [
            'baseDir' => '/fake/root',
            'path' => 'C:\some\absolute\path',
            'expectedPath' => 'C:/some/absolute/path',
        ];
        yield 'Valid absolute windows path is returned trimmed keeping drive letter' => [
            'baseDir' => '/fake/root',
            'path' => 'C:\some\absolute\path\\',
            'expectedPath' => 'C:/some/absolute/path',
        ];
        yield 'Single slash is returned as single slash' => [
            'baseDir' => '/fake/root',
            'path' => '/',
            'expectedPath' => '/',
        ];
    }

    /**
     * @dataProvider realpathDataSets
     * @test
     */
    public function realpathReturnsExpectedValue(string $baseDir, string $path, string $expectedPath): void
    {
        $realpathMethodInvoker = $this->createClassMethodInvoker(Config::class, 'realpath');
        self::assertSame($expectedPath, $realpathMethodInvoker->invoke(new Config($baseDir), $path));
    }

    public static function handleRootPackageExtraConfigReturnsExpectedConfigArrayDataSets(): \Generator
    {
        yield 'Empty extra config returns empty array' => [
            'extraConfig' => [],
            'expectedConfigArray' => [],
            'expectedOutput' => '',
        ];
        yield 'Other extra configuration are kept' => [
            'extraConfig' => [
                'typo3/cms' => [
                    'extension-key' => 'some_extension_key',
                ],
            ],
            'expectedConfigArray' => [
                'typo3/cms' => [
                    'extension-key' => 'some_extension_key',
                ],
            ],
            'expectedOutput' => '',
        ];
        yield 'Non-array extra->sbuerk/fixture-packages/paths value is removed' => [
            'extraConfig' => [
                'sbuerk/fixture-packages' => [
                    'paths' => false,
                ],
            ],
            'expectedConfigArray' => [
                'sbuerk/fixture-packages' => [],
            ],
            'expectedOutput' => '<warning>extra->sbuerk/fixture-packages/paths must be an array, "boolean" given.</warning>' . PHP_EOL,
        ];
        yield 'Non-array extra->sbuerk/fixture-packages/paths value is removed keeping other settings' => [
            'extraConfig' => [
                'sbuerk/fixture-packages' => [
                    'other-configuration' => true,
                    'paths' => false,
                ],
            ],
            'expectedConfigArray' => [
                'sbuerk/fixture-packages' => [
                    'other-configuration' => true,
                ],
            ],
            'expectedOutput' => '<warning>extra->sbuerk/fixture-packages/paths must be an array, "boolean" given.</warning>' . PHP_EOL,
        ];
    }

    /**
     * @dataProvider handleRootPackageExtraConfigReturnsExpectedConfigArrayDataSets
     * @test
     */
    public function handleRootPackageExtraConfigReturnsExpectedConfigArray(array $extraConfig, array $expectedConfigArray, string $expectedOutput): void
    {
        $rootPackage = new RootPackage('fake/package', '1.0.0', '1.0.0.0');
        $rootPackage->setExtra($extraConfig);
        $methodReflection =  $this->createClassMethodInvoker(Config::class, 'handleRootPackageExtraConfig');
        $bufferedIO = new BufferIO();
        self::assertSame($expectedConfigArray, $methodReflection->invoke(new Config('/fake/root'), $bufferedIO, $rootPackage));
        self::assertSame($expectedOutput, $bufferedIO->getOutput());
    }

    public static function loadCreatesConfigWithExpectedPathsDataSets(): \Generator
    {
        yield 'Empty extra config returns empty array' => [
            'extraConfig' => [],
            'expectedPaths' => [],
            'expectedOutput' => '',
        ];
        yield 'Other extra configuration are kept' => [
            'extraConfig' => [
                'typo3/cms' => [
                    'extension-key' => 'some_extension_key',
                ],
            ],
            'expectedPaths' => [],
            'expectedOutput' => '',
        ];
        yield 'Non-array extra->sbuerk/fixture-packages/paths value is removed' => [
            'extraConfig' => [
                'sbuerk/fixture-packages' => [
                    'paths' => false,
                ],
            ],
            'expectedPaths' => [],
            'expectedOutput' => '<warning>extra->sbuerk/fixture-packages/paths must be an array, "boolean" given.</warning>' . PHP_EOL,
        ];
        yield 'Non-array extra->sbuerk/fixture-packages/paths value is removed keeping other settings' => [
            'extraConfig' => [
                'sbuerk/fixture-packages' => [
                    'other-configuration' => true,
                    'paths' => false,
                ],
            ],
            'expectedPaths' => [],
            'expectedOutput' => '<warning>extra->sbuerk/fixture-packages/paths must be an array, "boolean" given.</warning>' . PHP_EOL,
        ];
    }

    /**
     * @dataProvider loadCreatesConfigWithExpectedPathsDataSets
     * @test
     */
    public function loadCreatesConfigWithExpectedPaths(array $extraConfig, array $expectedPaths, string $expectedOutput): void
    {
        $rootPackage = new RootPackage('fake/package', '1.0.0', '1.0.0.0');
        $rootPackage->setExtra($extraConfig);
        $composerConfig = new \Composer\Config(false, '/fake/root');
        $composer = new Composer();
        $composer->setPackage($rootPackage);
        $composer->setConfig($composerConfig);
        $bufferedIO = new BufferIO();
        $config = Config::load($composer, $bufferedIO);
        self::assertInstanceOf(Config::class, $config);
        self::assertSame($expectedPaths, $config->paths(Config::FLAG_PATHS_RELATIVE));
        self::assertSame($expectedOutput, $bufferedIO->getOutput());
    }
}
