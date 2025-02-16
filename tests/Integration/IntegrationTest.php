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

use Composer\IO\BufferIO;
use Composer\IO\IOInterface;
use SBUERK\AvailableFixturePackages;
use Symfony\Component\Console\Output\StreamOutput;

final class IntegrationTest extends IntegrationTestCase
{
    private ?IOInterface $io = null;

    protected function setUp(): void
    {
        $this->io = new BufferIO('', StreamOutput::VERBOSITY_DEBUG);
        parent::setUp();
        $this->copyInstanceTemplate('integration');
        $this->composerInstall($this->testInstancePath(), $this->io);
    }

    private function createAvailableFixturePackagesForFile(string $file): AvailableFixturePackages
    {
        $subject = new AvailableFixturePackages();
        $property = new \ReflectionProperty($subject, 'dataFile');
        if (PHP_VERSION_ID < 801000) {
            $property->setAccessible(true);
        }
        $property->setValue($subject, $file);
        return $subject;
    }

    private function integrationTestInstanceExpectedTypo3Extensions(string $expectedRelativePath): array
    {
        return [
            'vendor/test-extension-one' => [
                'name' => 'vendor/test-extension-one',
                'type' => 'typo3-cms-extension',
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
    }

    /**
     * @test
     */
    public function fixturePackagesFileExists(): void
    {
        self::assertFileExists($this->testInstancePath() . '/vendor/sbuerk/fixture-packages.php');
    }

    /**
     * @test
     */
    public function availableFixturePackagesClassFileExists(): void
    {
        self::assertFileExists($this->testInstancePath() . '/vendor/sbuerk/AvailableFixturePackages.php');
    }

    /**
     * @test
     */
    public function exportFileReturnsExpectedExport(): void
    {
        $this->fixturePackagesFileExists();
        $this->availableFixturePackagesClassFileExists();

        $expectedRelativePath = $this->testInstancePath() . '/vendor/sbuerk/../..';
        $expected = $this->integrationTestInstanceExpectedTypo3Extensions($expectedRelativePath);

        self::assertSame($expected, include $this->testInstancePath() . '/vendor/sbuerk/fixture-packages.php');
    }

    /**
     * @test
     */
    public function availableFixturePackagesPackagesReturnExpectedPackageNames(): void
    {
        $this->fixturePackagesFileExists();
        $this->availableFixturePackagesClassFileExists();

        $expectedRelativePath = $this->testInstancePath() . '/vendor/sbuerk/../..';
        $expected = array_keys($this->integrationTestInstanceExpectedTypo3Extensions($expectedRelativePath));

        $subject = $this->createAvailableFixturePackagesForFile($this->testInstancePath() . '/vendor/sbuerk/fixture-packages.php');
        self::assertSame($expected, $subject->packageNames());
    }
}
