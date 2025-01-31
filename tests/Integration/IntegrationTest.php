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
use Symfony\Component\Console\Output\StreamOutput;

final class IntegrationTest extends IntegrationTestCase
{
    private ?IOInterface $io = null;

    private bool $created = false;
    protected function setUp(): void
    {
        $this->io = new BufferIO('', StreamOutput::VERBOSITY_DEBUG);
        parent::setUp();
        if (!$this->created) {
            $this->copyInstanceTemplate('integration');
            $this->composerInstall($this->testInstancePath(), $this->io);
            $this->created = true;
        } else {
            if (!is_dir($this->testInstancePath())) {
                self::fail('Test instance does not exists from first setup.');
            }
            chdir($this->testInstancePath());
        }
    }

    /**
     * @test
     */
    public function exportFileExists(): void
    {
        self::assertFileExists($this->testInstancePath() . '/vendor/sbuerk/fixture-packages.php');
    }

    /**
     * @depends exportFileExists
     * @test
     */
    public function exportFileReturnsExpectedExport(): void
    {
        $expectedRelativePath = $this->testInstancePath() . '/vendor/sbuerk/../..';
        $expected = [
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
        self::assertSame($expected, include $this->testInstancePath() . '/vendor/sbuerk/fixture-packages.php');
    }
}
