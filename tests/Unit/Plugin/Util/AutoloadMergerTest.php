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

namespace SBUERK\FixturePackages\Tests\Unit\Plugin\Util;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Package\BasePackage;
use Composer\Package\Package;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackage;
use Composer\Package\RootPackageInterface;
use SBUERK\FixturePackages\Composer\Repository\FixturePathRepository;
use SBUERK\FixturePackages\Plugin\Util\AutoloadMerger;
use SBUERK\FixturePackages\Tests\Unit\BaseUnitTestCase;

/**
 * @covers \SBUERK\FixturePackages\Plugin\Util\AutoloadMerger
 */
final class AutoloadMergerTest extends BaseUnitTestCase
{
    /**
     * Create pseudo package instance to be used in tests.
     *
     * @param string $name
     * @param string $distUrl
     * @param array{"psr-0"?: array<string, array<string>|string>, "psr-4"?: array<string, array<string>|string>, classmap?: list<string>, files?: list<string>} $autoload
     * @param array{"psr-0"?: array<string, array<string>|string>, "psr-4"?: array<string, array<string>|string>, classmap?: list<string>, files?: list<string>} $devAutoload
     * @return BasePackage
     */
    private static function createPackage(
        string $name,
        string $distUrl,
        array $autoload = [],
        array $devAutoload = []
    ): BasePackage {
        $package = new Package($name, '1.0.0', '1.0.0.0');
        $package->setType('library');
        $package->setDistType('');
        $package->setDistUrl($distUrl);
        $package->setAutoload($autoload);
        $package->setDevAutoload($devAutoload);
        return $package;
    }

    /**
     * Create pseudo package instance to be used in tests.
     *
     * @param string $name
     * @param array{"psr-0"?: array<string, array<string>|string>, "psr-4"?: array<string, array<string>|string>, classmap?: list<string>, files?: list<string>} $autoload
     * @param array{"psr-0"?: array<string, array<string>|string>, "psr-4"?: array<string, array<string>|string>, classmap?: list<string>, files?: list<string>} $devAutoload
     * @return RootPackageInterface
     */
    private static function createRootPackage(
        string $name,
        array $autoload = [],
        array $devAutoload = []
    ): RootPackageInterface {
        $package = new RootPackage($name, '1.0.0', '1.0.0.0');
        $package->setType('project');
        $package->setAutoload($autoload);
        $package->setDevAutoload($devAutoload);
        return $package;
    }

    private function createFixturePathRepository(
        IOInterface $io,
        array $selection = ['autoload']
    ): FixturePathRepository {
        return new FixturePathRepository(
            [
                'url' => '',
                'type' => '',
                'selection' => $selection,
            ],
            $io,
            new Config(false, null),
        );
    }

    public static function modifyPackageNamespacePathDataSets(): \Generator
    {
        yield 'namespacePath is prefixed with package sourceUrl' => [
            'package' => self::createPackage('fake/package', 'some/path/fake_package'),
            'namespacePath' => 'Classes',
            'expectedReturnValue' => 'some/path/fake_package/Classes',
        ];
        yield 'namespacePath with ending slash is prefixed with package sourceUrl' => [
            'package' => self::createPackage('fake/package', 'some/path/fake_package'),
            'namespacePath' => 'Classes/',
            'expectedReturnValue' => 'some/path/fake_package/Classes',
        ];
        yield 'back-and-forth relative namespacePath is prefixed with package sourceUrl and normalized' => [
            'package' => self::createPackage('fake/package', 'some/path/fake_package'),
            'namespacePath' => 'Classes/Subfolder/../../src',
            'expectedReturnValue' => 'some/path/fake_package/src',
        ];
    }

    /**
     * @dataProvider modifyPackageNamespacePathDataSets
     * @test
     */
    public function modifyPackageNamespacePathReturnsExpectedString(
        PackageInterface $package,
        string $namespacePath,
        string $expectedReturnValue
    ): void {
        $io = new NullIO();
        $package->setRepository($this->createFixturePathRepository($io));
        $invoker = $this->createClassMethodInvoker(AutoloadMerger::class, 'modifyPackageNamespacePath');
        self::assertSame($expectedReturnValue, $invoker->invoke(new AutoloadMerger(), $io, $package, $namespacePath));
    }

    public static function mergePsrNamespaceDataSets(): \Generator
    {
        // psr-4
        yield 'Non existing PSR-4 namespace is added along with PSR-4 main section, not creating unrelated sections' => [
            'rootPackage' => self::createRootPackage('project/root'),
            'package' => self::createPackage('fake/package', 'packages/fake_package'),
            'psr' => 'psr-4',
            'namespace' => 'Vendor\\Package\\',
            'namespacePath' => 'Classes',
            'expectedRootPackageAutoloadDev' => [
                'psr-4' => [
                    'Vendor\\Package\\' => 'packages/fake_package/Classes',
                ],
            ],
        ];
        yield 'Existing PSR-4 namespace with matching path does not convert to array, not creating unrelated sections' => [
            'rootPackage' => self::createRootPackage('project/root', [], ['psr-4' => ['Vendor\\Package\\' => 'packages/fake_package/Classes']]),
            'package' => self::createPackage('fake/package', 'packages/fake_package'),
            'psr' => 'psr-4',
            'namespace' => 'Vendor\\Package\\',
            'namespacePath' => 'Classes',
            'expectedRootPackageAutoloadDev' => [
                'psr-4' => [
                    'Vendor\\Package\\' => 'packages/fake_package/Classes',
                ],
            ],
        ];
        yield 'Existing PSR-4 namespace adds additional path converting to array, not creating unrelated sections' => [
            'rootPackage' => self::createRootPackage('project/root', [], ['psr-4' => ['Vendor\\Package\\' => 'packages/main_package/src']]),
            'package' => self::createPackage('fake/package', 'packages/fake_package'),
            'psr' => 'psr-4',
            'namespace' => 'Vendor\\Package\\',
            'namespacePath' => 'Classes',
            'expectedRootPackageAutoloadDev' => [
                'psr-4' => [
                    'Vendor\\Package\\' => [
                        'packages/main_package/src',
                        'packages/fake_package/Classes',
                    ],
                ],
            ],
        ];
        // psr-0
        yield 'Non existing PSR-0 namespace is added along with PSR-4 main section, not creating unrelated sections' => [
            'rootPackage' => self::createRootPackage('project/root'),
            'package' => self::createPackage('fake/package', 'packages/fake_package'),
            'psr' => 'psr-0',
            'namespace' => 'Vendor\\Package\\',
            'namespacePath' => 'Classes',
            'expectedRootPackageAutoloadDev' => [
                'psr-0' => [
                    'Vendor\\Package\\' => 'packages/fake_package/Classes',
                ],
            ],
        ];
        yield 'Existing PSR-0 namespace with matching path does not convert to array, not creating unrelated sections' => [
            'rootPackage' => self::createRootPackage('project/root', [], ['psr-0' => ['Vendor\\Package\\' => 'packages/fake_package/Classes']]),
            'package' => self::createPackage('fake/package', 'packages/fake_package'),
            'psr' => 'psr-0',
            'namespace' => 'Vendor\\Package\\',
            'namespacePath' => 'Classes',
            'expectedRootPackageAutoloadDev' => [
                'psr-0' => [
                    'Vendor\\Package\\' => 'packages/fake_package/Classes',
                ],
            ],
        ];
        yield 'Existing PSR-0 namespace adds additional path converting to array, not creating unrelated sections' => [
            'rootPackage' => self::createRootPackage('project/root', [], ['psr-0' => ['Vendor\\Package\\' => 'packages/main_package/src']]),
            'package' => self::createPackage('fake/package', 'packages/fake_package'),
            'psr' => 'psr-0',
            'namespace' => 'Vendor\\Package\\',
            'namespacePath' => 'Classes',
            'expectedRootPackageAutoloadDev' => [
                'psr-0' => [
                    'Vendor\\Package\\' => [
                        'packages/main_package/src',
                        'packages/fake_package/Classes',
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider mergePsrNamespaceDataSets
     * @test
     */
    public function mergePsrNamespaceModifiesRootPackageAsExpected(
        RootPackageInterface $rootPackage,
        BasePackage $package,
        string $psr,
        string $namespace,
        string $namespacePath,
        array $expectedRootPackageAutoloadDev
    ): void {
        $io = new NullIO();
        $package->setRepository($this->createFixturePathRepository($io));
        $invoker = $this->createClassMethodInvoker(AutoloadMerger::class, 'mergePsrNamespace');
        $invoker->invoke(new AutoloadMerger(), $io, $rootPackage, $package, $psr, $namespace, $namespacePath);
        self::assertSame($expectedRootPackageAutoloadDev, $rootPackage->getDevAutoload());
    }

    public static function mergePsrNamespacesDataSets(): \Generator
    {
        yield 'psr-4 namespaces are merged to root package' => [
            'rootPackage' => self::createRootPackage('project/root'),
            'package' => self::createPackage('fake/package', 'packages/fake_package', ['psr-4' => ['Vendor\\Package\\' => 'Classes', 'Vendor\\Package\\SecondNamespace\\' => 'src']]),
            'psr' => 'psr-4',
            'expectedDevAutoload' => [
                'psr-4' => [
                    'Vendor\\Package\\' => 'packages/fake_package/Classes',
                    'Vendor\\Package\\SecondNamespace\\' => 'packages/fake_package/src',
                ],
            ],
        ];
    }

    public static function mergeSimpleNamespaceDataSets(): \Generator
    {
        yield 'files namespaces are adopted' => [
            'rootPackage' => self::createRootPackage('project/root'),
            'package' => self::createPackage(
                'fake/package',
                'packages/fake_package',
                [
                    'files' => [
                        'some/path/',
                        'other/single.php',
                    ],
                ]
            ),
            'type' => 'files',
            'expectedDevAutoload' => [
                'files' => [
                    'packages/fake_package/some/path',
                    'packages/fake_package/other/single.php',
                ],
            ],
        ];
        yield 'classmap namespaces are adopted' => [
            'rootPackage' => self::createRootPackage('project/root'),
            'package' => self::createPackage(
                'fake/package',
                'packages/fake_package',
                [
                    'classmap' => [
                        'some/path/',
                        'other/single.php',
                    ],
                ]
            ),
            'type' => 'classmap',
            'expectedDevAutoload' => [
                'classmap' => [
                    'packages/fake_package/some/path',
                    'packages/fake_package/other/single.php',
                ],
            ],
        ];
    }

    /**
     * @dataProvider mergeSimpleNamespaceDataSets
     * @test
     */
    public function mergeSimpleNamespaceModifiesRootPackageAsExpected(
        RootPackageInterface $rootPackage,
        BasePackage $package,
        string $type,
        array $expectedDevAutoload
    ): void {
        $io = new NullIO();
        $package->setRepository($this->createFixturePathRepository($io));
        $invoker = $this->createClassMethodInvoker(AutoloadMerger::class, 'mergeAutoloadSimpleNamespace');
        $invoker->invoke(new AutoloadMerger(), $io, $rootPackage, $package, $type);
        self::assertSame($expectedDevAutoload, $rootPackage->getDevAutoload());
    }

    /**
     * @dataProvider mergePsrNamespacesDataSets
     * @test
     */
    public function mergePsrNamespacesModifiesRootPackageAsExpected(
        RootPackageInterface $rootPackage,
        BasePackage $package,
        string $psr,
        array $expectedDevAutoload
    ): void {
        $io = new NullIO();
        $package->setRepository($this->createFixturePathRepository($io));
        $invoker = $this->createClassMethodInvoker(AutoloadMerger::class, 'mergeAutoloadPsrNamespaces');
        $invoker->invoke(new AutoloadMerger(), $io, $rootPackage, $package, $psr);
        self::assertSame($expectedDevAutoload, $rootPackage->getDevAutoload());
    }

    public static function mergeAllNamespacesDataSets(): \Generator
    {
        yield 'psr-0 and psr-4 namespaces are merged to root-package (only autoload)' => [
            'rootPackage' => self::createRootPackage('project/root'),
            'package' => self::createPackage(
                'fake/package',
                'packages/fake_package',
                [
                    'psr-0' => [
                        'Vendor_Namespace\\' => 'src-psr0',
                    ],
                    'psr-4' => [
                        'Vendor\\Package\\' => 'src-psr4',
                    ],
                ],
                [
                    'psr-0' => [
                        'Vendor_Namespace\\Tests\\' => 'tests-psr0',
                    ],
                    'psr-4' => [
                        'Vendor\\Package\\Tests\\' => 'tests-psr4',
                    ],
                ]
            ),
            'selection' => [
                'autoload',
            ],
            'expectedDevAutoload' => [
                'psr-0' => [
                    'Vendor_Namespace\\' => 'packages/fake_package/src-psr0',
                ],
                'psr-4' => [
                    'Vendor\\Package\\' => 'packages/fake_package/src-psr4',
                ],
            ],
        ];
        yield 'psr-0 and psr-4 namespaces are merged to root-package (only autoload-dev)' => [
            'rootPackage' => self::createRootPackage('project/root'),
            'package' => self::createPackage(
                'fake/package',
                'packages/fake_package',
                [
                    'psr-0' => [
                        'Vendor_Namespace\\' => 'src-psr0',
                    ],
                    'psr-4' => [
                        'Vendor\\Package\\' => 'src-psr4',
                    ],
                ],
                [
                    'psr-0' => [
                        'Vendor_Namespace\\Tests\\' => 'tests-psr0',
                    ],
                    'psr-4' => [
                        'Vendor\\Package\\Tests\\' => 'tests-psr4',
                    ],
                ]
            ),
            'selection' => [
                'autoload-dev',
            ],
            'expectedDevAutoload' => [
                'psr-0' => [
                    'Vendor_Namespace\\Tests\\' => 'packages/fake_package/tests-psr0',
                ],
                'psr-4' => [
                    'Vendor\\Package\\Tests\\' => 'packages/fake_package/tests-psr4',
                ],
            ],
        ];
        yield 'psr-0 and psr-4 namespaces are merged to root-package (autoload and autoload-dev)' => [
            'rootPackage' => self::createRootPackage('project/root'),
            'package' => self::createPackage(
                'fake/package',
                'packages/fake_package',
                [
                    'psr-0' => [
                        'Vendor_Namespace\\' => 'src-psr0',
                    ],
                    'psr-4' => [
                        'Vendor\\Package\\' => 'src-psr4',
                    ],
                ],
                [
                    'psr-0' => [
                        'Vendor_Namespace\\Tests\\' => 'tests-psr0',
                    ],
                    'psr-4' => [
                        'Vendor\\Package\\Tests\\' => 'tests-psr4',
                    ],
                ]
            ),
            'selection' => [
                'autoload',
                'autoload-dev',
            ],
            'expectedDevAutoload' => [
                'psr-0' => [
                    'Vendor_Namespace\\' => 'packages/fake_package/src-psr0',
                    'Vendor_Namespace\\Tests\\' => 'packages/fake_package/tests-psr0',
                ],
                'psr-4' => [
                    'Vendor\\Package\\' => 'packages/fake_package/src-psr4',
                    'Vendor\\Package\\Tests\\' => 'packages/fake_package/tests-psr4',
                ],
            ],
        ];
        yield 'psr-0 and psr-4 namespaces are merged with same root-package namespaces converted to array (only autoload)' => [
            'rootPackage' => self::createRootPackage(
                'project/root',
                [],
                [
                    'psr-0' => [
                        'Vendor_Namespace\\' => 'other-package/src',
                        'Vendor_Namespace\\Tests\\' => 'other-package/tests',
                    ],
                    'psr-4' => [
                        'Vendor\\Package\\' => 'other-package/Classes',
                        'Vendor\\Package\\Tests\\' => 'other-package/Tests',
                    ],
                ],
            ),
            'package' => self::createPackage(
                'fake/package',
                'packages/fake_package',
                [
                    'psr-0' => [
                        'Vendor_Namespace\\' => 'src-psr0',
                    ],
                    'psr-4' => [
                        'Vendor\\Package\\' => 'src-psr4',
                    ],
                ],
                [
                    'psr-0' => [
                        'Vendor_Namespace\\Tests\\' => 'tests-psr0',
                    ],
                    'psr-4' => [
                        'Vendor\\Package\\Tests\\' => 'tests-psr4',
                    ],
                ]
            ),
            'selection' => [
                'autoload',
            ],
            'expectedDevAutoload' => [
                'psr-0' => [
                    'Vendor_Namespace\\' => [
                        'other-package/src',
                        'packages/fake_package/src-psr0',
                    ],
                    'Vendor_Namespace\\Tests\\' => 'other-package/tests',
                ],
                'psr-4' => [
                    'Vendor\\Package\\' => [
                        'other-package/Classes',
                        'packages/fake_package/src-psr4',
                    ],
                    'Vendor\\Package\\Tests\\' => 'other-package/Tests',
                ],
            ],
        ];
        yield 'psr-0 and psr-4 namespaces are merged with same root-package namespaces converted to array (only autoload-dev)' => [
            'rootPackage' => self::createRootPackage(
                'project/root',
                [],
                [
                    'psr-0' => [
                        'Vendor_Namespace\\' => 'other-package/src',
                        'Vendor_Namespace\\Tests\\' => 'other-package/tests',
                    ],
                    'psr-4' => [
                        'Vendor\\Package\\' => 'other-package/Classes',
                        'Vendor\\Package\\Tests\\' => 'other-package/Tests',
                    ],
                ],
            ),
            'package' => self::createPackage(
                'fake/package',
                'packages/fake_package',
                [
                    'psr-0' => [
                        'Vendor_Namespace\\' => 'src-psr0',
                    ],
                    'psr-4' => [
                        'Vendor\\Package\\' => 'src-psr4',
                    ],
                ],
                [
                    'psr-0' => [
                        'Vendor_Namespace\\Tests\\' => 'tests-psr0',
                    ],
                    'psr-4' => [
                        'Vendor\\Package\\Tests\\' => 'tests-psr4',
                    ],
                ]
            ),
            'selection' => [
                'autoload-dev',
            ],
            'expectedDevAutoload' => [
                'psr-0' => [
                    'Vendor_Namespace\\' => 'other-package/src',
                    'Vendor_Namespace\\Tests\\' => [
                        'other-package/tests',
                        'packages/fake_package/tests-psr0',
                    ],
                ],
                'psr-4' => [
                    'Vendor\\Package\\' => 'other-package/Classes',
                    'Vendor\\Package\\Tests\\' => [
                        'other-package/Tests',
                        'packages/fake_package/tests-psr4',
                    ],
                ],
            ],
        ];
        yield 'psr-0 and psr-4 namespaces are merged with same root-package namespaces converted to array (autoload and autoload-dev)' => [
            'rootPackage' => self::createRootPackage(
                'project/root',
                [],
                [
                    'psr-0' => [
                        'Vendor_Namespace\\' => 'other-package/src',
                        'Vendor_Namespace\\Tests\\' => 'other-package/tests',
                    ],
                    'psr-4' => [
                        'Vendor\\Package\\' => 'other-package/Classes',
                        'Vendor\\Package\\Tests\\' => 'other-package/Tests',
                    ],
                ],
            ),
            'package' => self::createPackage(
                'fake/package',
                'packages/fake_package',
                [
                    'psr-0' => [
                        'Vendor_Namespace\\' => 'src-psr0',
                    ],
                    'psr-4' => [
                        'Vendor\\Package\\' => 'src-psr4',
                    ],
                ],
                [
                    'psr-0' => [
                        'Vendor_Namespace\\Tests\\' => 'tests-psr0',
                    ],
                    'psr-4' => [
                        'Vendor\\Package\\Tests\\' => 'tests-psr4',
                    ],
                ]
            ),
            'selection' => [
                'autoload',
                'autoload-dev',
            ],
            'expectedDevAutoload' => [
                'psr-0' => [
                    'Vendor_Namespace\\' => [
                        'other-package/src',
                        'packages/fake_package/src-psr0',
                    ],
                    'Vendor_Namespace\\Tests\\' => [
                        'other-package/tests',
                        'packages/fake_package/tests-psr0',
                    ],
                ],
                'psr-4' => [
                    'Vendor\\Package\\' => [
                        'other-package/Classes',
                        'packages/fake_package/src-psr4',
                    ],
                    'Vendor\\Package\\Tests\\' => [
                        'other-package/Tests',
                        'packages/fake_package/tests-psr4',
                    ],
                ],
            ],
        ];
        yield 'files namespaces are adopted (only autoload)' => [
            'rootPackage' => self::createRootPackage('project/root'),
            'package' => self::createPackage(
                'fake/package',
                'packages/fake_package',
                [
                    'files' => [
                        'some/path/',
                        'other/single.php',
                    ],
                ],
                [
                    'files' => [
                        'tests/fixtures/path',
                        'fixturs/file.php',
                    ],
                ]
            ),
            'selection' => [
                'autoload',
            ],
            'expectedDevAutoload' => [
                'files' => [
                    'packages/fake_package/some/path',
                    'packages/fake_package/other/single.php',
                ],
            ],
        ];
        yield 'files namespaces are adopted (only autoload-dev)' => [
            'rootPackage' => self::createRootPackage('project/root'),
            'package' => self::createPackage(
                'fake/package',
                'packages/fake_package',
                [
                    'files' => [
                        'some/path/',
                        'other/single.php',
                    ],
                ],
                [
                    'files' => [
                        'tests/fixtures/path',
                        'fixturs/file.php',
                    ],
                ]
            ),
            'selection' => [
                'autoload-dev',
            ],
            'expectedDevAutoload' => [
                'files' => [
                    'packages/fake_package/tests/fixtures/path',
                    'packages/fake_package/fixturs/file.php',
                ],
            ],
        ];
        yield 'files namespaces are adopted (autoload and autoload-dev)' => [
            'rootPackage' => self::createRootPackage('project/root'),
            'package' => self::createPackage(
                'fake/package',
                'packages/fake_package',
                [
                    'files' => [
                        'some/path/',
                        'other/single.php',
                    ],
                ],
                [
                    'files' => [
                        'tests/fixtures/path',
                        'fixturs/file.php',
                    ],
                ]
            ),
            'selection' => [
                'autoload',
                'autoload-dev',
            ],
            'expectedDevAutoload' => [
                'files' => [
                    'packages/fake_package/some/path',
                    'packages/fake_package/other/single.php',
                    'packages/fake_package/tests/fixtures/path',
                    'packages/fake_package/fixturs/file.php',
                ],
            ],
        ];
        yield 'classmap namespaces are adopted (only autoload)' => [
            'rootPackage' => self::createRootPackage('project/root'),
            'package' => self::createPackage(
                'fake/package',
                'packages/fake_package',
                [
                    'classmap' => [
                        'some/path/',
                        'other/single.php',
                    ],
                ],
                [
                    'classmap' => [
                        'tests/path/',
                        'tests/single.php',
                    ],
                ]
            ),
            'selection' => [
                'autoload',
            ],
            'expectedDevAutoload' => [
                'classmap' => [
                    'packages/fake_package/some/path',
                    'packages/fake_package/other/single.php',
                ],
            ],
        ];
        yield 'classmap namespaces are adopted (only autoload-dev)' => [
            'rootPackage' => self::createRootPackage('project/root'),
            'package' => self::createPackage(
                'fake/package',
                'packages/fake_package',
                [
                    'classmap' => [
                        'some/path/',
                        'other/single.php',
                    ],
                ],
                [
                    'classmap' => [
                        'tests/path/',
                        'tests/single.php',
                    ],
                ]
            ),
            'selection' => [
                'autoload-dev',
            ],
            'expectedDevAutoload' => [
                'classmap' => [
                    'packages/fake_package/tests/path',
                    'packages/fake_package/tests/single.php',
                ],
            ],
        ];
        yield 'classmap namespaces are adopted (autoload and autoload-dev)' => [
            'rootPackage' => self::createRootPackage('project/root'),
            'package' => self::createPackage(
                'fake/package',
                'packages/fake_package',
                [
                    'classmap' => [
                        'some/path/',
                        'other/single.php',
                    ],
                ],
                [
                    'classmap' => [
                        'tests/path/',
                        'tests/single.php',
                    ],
                ]
            ),
            'selection' => [
                'autoload',
                'autoload-dev',
            ],
            'expectedDevAutoload' => [
                'classmap' => [
                    'packages/fake_package/some/path',
                    'packages/fake_package/other/single.php',
                    'packages/fake_package/tests/path',
                    'packages/fake_package/tests/single.php',
                ],
            ],
        ];
    }

    /**
     * @dataProvider mergeAllNamespacesDataSets
     * @test
     */
    public function mergeAllNamespacesModifiesRootPackageAsExpected(
        RootPackageInterface $rootPackage,
        BasePackage $package,
        array $selection,
        array $expectedDevAutoload
    ): void {
        $io = new NullIO();
        $package->setRepository($this->createFixturePathRepository($io, $selection));
        $invoker = $this->createClassMethodInvoker(AutoloadMerger::class, 'mergeAllNamespaces');
        $invoker->invoke(new AutoloadMerger(), $io, $rootPackage, $package);
        self::assertSame($expectedDevAutoload, $rootPackage->getDevAutoload());
    }
}
