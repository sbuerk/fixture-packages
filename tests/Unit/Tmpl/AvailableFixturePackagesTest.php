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

namespace SBUERK\FixturePackages\Tests\Unit\Tmpl;

use SBUERK\AvailableFixturePackages;
use SBUERK\FixturePackages\Tests\Unit\BaseUnitTestCase;

final class AvailableFixturePackagesTest extends BaseUnitTestCase
{
    private function createSubject(
        ?string $stateFileToUse = null,
        ?array $stateToSet = null,
        ?string $composerPackageNameClassFile = null
    ): AvailableFixturePackages {
        $subject = new AvailableFixturePackages();
        if ($stateFileToUse !== null) {
            $this->setClassPropertyValue(
                $subject,
                'dataFile',
                $stateFileToUse
            );
        }
        if ($stateToSet !== null) {
            $this->setClassPropertyValue(
                $subject,
                'packages',
                $stateToSet
            );
        }
        if ($composerPackageNameClassFile !== null) {
            $this->setClassPropertyValue(
                $subject,
                'composerPackageManagerClassName',
                $composerPackageNameClassFile
            );
        }
        return $subject;
    }

    public static function packagesFilterDataSets(): \Generator
    {
        $expectedRelativePath = '/fake/root';
        $packages = [
            'vendor/test-extension-one' => [
                'name' => 'vendor/test-extension-one',
                'type' => 'typo3-cms-framework',
                'path' => $expectedRelativePath . '/Fixtures/Extensions/extension-one',
                'extra' => [
                    'typo3/cms' => [
                        'extension-key' => 'extension_one',
                    ],
                ],
            ],
            'vendor/test-extension-two' => [
                'name' => 'vendor/test-extension-two',
                'type' => 'typo3-cms-extension',
                'path' => $expectedRelativePath . '/Fixtures/Extensions/extension-two',
                'extra' => [
                    'typo3/cms' => [
                        'extension-key' => 'extension_two',
                    ],
                ],
            ],
            'vendor/test-extension-three' => [
                'name' => 'vendor/test-extension-three',
                'type' => 'typo3-cms-extension',
                'path' => $expectedRelativePath . '/Fixtures/Mixed/extension-three',
                'extra' => [
                    'typo3/cms' => [
                        'extension-key' => 'extension_three',
                    ],
                ],
            ],
            'vendor/test-package-two' => [
                'name' => 'vendor/test-package-two',
                'type' => 'library',
                'path' => $expectedRelativePath . '/Fixtures/Mixed/package-two',
                'extra' => [],
            ],
            'vendor/test-package-one' => [
                'name' => 'vendor/test-package-one',
                'type' => 'library',
                'path' => $expectedRelativePath . '/Fixtures/Packages/package-one',
                'extra' => [],
            ],
            'vendor/test-extension-four' => [
                'name' => 'vendor/test-extension-four',
                'type' => 'typo3-cms-extension',
                'path' => $expectedRelativePath . '/Packages/local-one/Fixtures/Extensions/extension-four',
                'extra' => [
                    'typo3/cms' => [
                        'extension-key' => 'extension_four',
                    ],
                ],
            ],
            'vendor/test-extension-five' => [
                'name' => 'vendor/test-extension-five',
                'type' => 'typo3-cms-extension',
                'path' => $expectedRelativePath . '/Packages/local-two/Fixtures/Extensions/extension-five',
                'extra' => [
                    'typo3/cms' => [
                        'extension-key' => 'extension_five',
                    ],
                ],
            ],
        ];
        yield 'library returns only packages of type "library"' => [
            'packages' => $packages,
            'filter' => [
                'library',
            ],
            'expectedPackages' => [
                'vendor/test-package-two' => [
                    'name' => 'vendor/test-package-two',
                    'type' => 'library',
                    'path' => $expectedRelativePath . '/Fixtures/Mixed/package-two',
                    'extra' => [],
                ],
                'vendor/test-package-one' => [
                    'name' => 'vendor/test-package-one',
                    'type' => 'library',
                    'path' => $expectedRelativePath . '/Fixtures/Packages/package-one',
                    'extra' => [],
                ],
            ],
        ];
        yield 'typo3-cms-framework returns only system extensions' => [
            'packages' => $packages,
            'filter' => [
                'typo3-cms-framework',
            ],
            'expectedPackages' => [
                'vendor/test-extension-one' => [
                    'name' => 'vendor/test-extension-one',
                    'type' => 'typo3-cms-framework',
                    'path' => $expectedRelativePath . '/Fixtures/Extensions/extension-one',
                    'extra' => [
                        'typo3/cms' => [
                            'extension-key' => 'extension_one',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider packagesFilterDataSets
     * @test
     */
    public function packagesFilterReturnsExpectedPackages(array $packages, array $filter, array $expectedPackages): void
    {
        $subject = $this->createSubject(null, $packages);
        self::assertSame($expectedPackages, $subject->packages($filter));
    }

    /**
     * @dataProvider packagesFilterDataSets
     * @test
     */
    public function packageNamesReturnsExpectedPackageNames(array $packages, array $filter, array $expectedPackages): void
    {
        $subject = $this->createSubject(null, $packages);
        self::assertSame(array_keys($expectedPackages), $subject->packageNames($filter));
    }

    /**
     * @dataProvider packagesFilterDataSets
     * @test
     */
    public function packageNamesAndPathsReturnsExpectedNamesAndPaths(array $packages, array $filter, array $expectedPackages): void
    {
        $subject = $this->createSubject(null, $packages);
        self::assertSame(array_map('strval', array_column($expectedPackages, 'path', 'name')), $subject->packageNamesAndPaths($filter));
    }
}
