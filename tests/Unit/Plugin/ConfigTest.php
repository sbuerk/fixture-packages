<?php

declare(strict_types=1);

namespace SBUERK\TestFixtureExtensionAdopter\Tests\Unit\Plugin;

use Composer\Composer;
use Composer\IO\BufferIO;
use Composer\Package\RootPackage;
use PHPUnit\Framework\TestCase;
use SBUERK\TestFixtureExtensionAdopter\Plugin\Config;

final class ConfigTest extends TestCase
{

    public function tearDown(): void
    {
        /**
         * Reset static property {@see Config::$instance} holding instance used within {@see Config::load()}. This
         * essential, otherwise tests will fail for different scenarios.
         */
        $this->createSubjectClassReflection()->setStaticPropertyValue('instance', null);
        parent::tearDown();
    }

    private function createSubject(string $baseDir = '/fake/root'): Config
    {
        return new Config($baseDir);
    }

    private function createSubjectMethodReflection(string $method): \ReflectionMethod
    {
        $methodReflection = $this->createSubjectClassReflection()->getMethod($method);
        if (PHP_VERSION_ID < 801000) {
            $methodReflection->setAccessible(true);
        }
        $methodReflection->setAccessible(true);
        return $methodReflection;
    }

    private function createSubjectClassReflection(): \ReflectionClass
    {
        return new \ReflectionClass(Config::class);
    }

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
            'expectedValue' => 'C:/',
        ];
        yield 'Windows path backslash is normalized to slash keeping drive letter' => [
            'value' => 'C:\\',
            'expectedValue' => 'C:/',
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

    public function normalizePathReturnsExpectedValue(string $value, string $expectedValue): void
    {
        $realpathMethodInvoker = $this->createSubjectMethodReflection('normalizePath');
        self::assertSame($expectedValue, $realpathMethodInvoker->invoke($this->createSubject(), $value));
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
        $realpathMethodInvoker = $this->createSubjectMethodReflection('realpath');
        self::assertSame($expectedPath, $realpathMethodInvoker->invoke($this->createSubject($baseDir), $path));
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
        yield 'Non-array extra->typo3/testing-framework/fixture-extension-paths value is removed' => [
            'extraConfig' => [
                'typo3/testing-framework' => [
                    'fixture-extension-paths' => false,
                ],
            ],
            'expectedConfigArray' => [
                'typo3/testing-framework' => [],
            ],
            'expectedOutput' => '<warning>extra->typo3/testing-framework/fixture-extension-paths must be an array, "boolean" given.</warning>' . PHP_EOL,
        ];
        yield 'Non-array extra->typo3/testing-framework/fixture-extension-paths value is removed keeping other settings' => [
            'extraConfig' => [
                'typo3/testing-framework' => [
                    'other-configuration' => true,
                    'fixture-extension-paths' => false,
                ],
            ],
            'expectedConfigArray' => [
                'typo3/testing-framework' => [
                    'other-configuration' => true,
                ],
            ],
            'expectedOutput' => '<warning>extra->typo3/testing-framework/fixture-extension-paths must be an array, "boolean" given.</warning>' . PHP_EOL,
        ];
        yield 'non-relative fixture extension paths are discarded and outputs warning' => [
            'extraConfig' => [
                'typo3/testing-framework' => [
                    'fixture-extension-paths' => [
                        '/absolute/path',
                    ],
                ],
            ],
            'expectedConfigArray' => [
                'typo3/testing-framework' => [
                    'fixture-extension-paths' => [],
                ],
            ],
            'expectedOutput' => sprintf(
                '<warning>Test fixture extension path must be a subdirectory of Composer root directory. Skip "%s".</warning>' . PHP_EOL,
                '/absolute/path'
            ),
        ];
        yield 'non-relative fixture exetension paths are discarded and outputs warning, but keeps relative paths' => [
            'extraConfig' => [
                'typo3/testing-framework' => [
                    'fixture-extension-paths' => [
                        '/absolute/path',
                        'relative/path',
                    ],
                ],
            ],
            'expectedConfigArray' => [
                'typo3/testing-framework' => [
                    'fixture-extension-paths' => [
                        'relative/path',
                    ],
                ],
            ],
            'expectedOutput' => sprintf(
                '<warning>Test fixture extension path must be a subdirectory of Composer root directory. Skip "%s".</warning>' . PHP_EOL,
                '/absolute/path'
            ),
        ];
        yield 'relative path leaving composer root is discarded and outputs warning' => [
            'extraConfig' => [
                'typo3/testing-framework' => [
                    'fixture-extension-paths' => [
                        'relative/../../path-outside-composer-root',
                    ],
                ],
            ],
            'expectedConfigArray' => [
                'typo3/testing-framework' => [
                    'fixture-extension-paths' => [],
                ],
            ],
            'expectedOutput' => sprintf(
                '<warning>Test fixture extension path must be a subdirectory of Composer root directory. Skip "%s".</warning>' . PHP_EOL,
                'relative/../../path-outside-composer-root'
            ),
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
        $methodReflection =  $this->createSubjectMethodReflection('handleRootPackageExtraConfig');
        $bufferedIO = new BufferIO();
        self::assertSame($expectedConfigArray, $methodReflection->invoke($this->createSubject(), $bufferedIO, $rootPackage));
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
        yield 'Non-array extra->typo3/testing-framework/fixture-extension-paths value is removed' => [
            'extraConfig' => [
                'typo3/testing-framework' => [
                    'fixture-extension-paths' => false,
                ],
            ],
            'expectedPaths' => [],
            'expectedOutput' => '<warning>extra->typo3/testing-framework/fixture-extension-paths must be an array, "boolean" given.</warning>' . PHP_EOL,
        ];
        yield 'Non-array extra->typo3/testing-framework/fixture-extension-paths value is removed keeping other settings' => [
            'extraConfig' => [
                'typo3/testing-framework' => [
                    'other-configuration' => true,
                    'fixture-extension-paths' => false,
                ],
            ],
            'expectedPaths' => [],
            'expectedOutput' => '<warning>extra->typo3/testing-framework/fixture-extension-paths must be an array, "boolean" given.</warning>' . PHP_EOL,
        ];
        yield 'non-relative fixture extension paths are discarded and outputs warning' => [
            'extraConfig' => [
                'typo3/testing-framework' => [
                    'fixture-extension-paths' => [
                        '/absolute/path',
                    ],
                ],
            ],
            'expectedPaths' => [],
            'expectedOutput' => sprintf(
                '<warning>Test fixture extension path must be a subdirectory of Composer root directory. Skip "%s".</warning>' . PHP_EOL,
                '/absolute/path'
            ),
        ];
        yield 'non-relative fixture exetension paths are discarded and outputs warning, but keeps relative paths' => [
            'extraConfig' => [
                'typo3/testing-framework' => [
                    'fixture-extension-paths' => [
                        '/absolute/path',
                        'relative/path',
                    ],
                ],
            ],
            'expectedPaths' => [
                'relative/path',
            ],
            'expectedOutput' => sprintf(
                '<warning>Test fixture extension path must be a subdirectory of Composer root directory. Skip "%s".</warning>' . PHP_EOL,
                '/absolute/path'
            ),
        ];
        yield 'relative path leaving composer root is discarded and outputs warning' => [
            'extraConfig' => [
                'typo3/testing-framework' => [
                    'fixture-extension-paths' => [
                        'relative/../../path-outside-composer-root',
                    ],
                ],
            ],
            'expectedPaths' => [],
            'expectedOutput' => sprintf(
                '<warning>Test fixture extension path must be a subdirectory of Composer root directory. Skip "%s".</warning>' . PHP_EOL,
                'relative/../../path-outside-composer-root'
            ),
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
        self::assertSame($expectedPaths, $config->fixtureExtensionPaths(Config::FLAG_PATHS_RELATIVE));
        self::assertSame($expectedOutput, $bufferedIO->getOutput());
    }
}