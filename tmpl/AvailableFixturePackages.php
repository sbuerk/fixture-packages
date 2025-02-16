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

namespace SBUERK;

/**
 * This file will be copied to `<vendor-dir>/sbuerk/AvailableFixturePackages.php`.
 */
final class AvailableFixturePackages
{
    private string $dataFile = __DIR__ . 'fixture-packages.php';
    private string $composerPackageManagerClassName = 'TYPO3\\TestingFramework\\Composer\\ComposerPackageManager';

    /**
     * @var array<string, array{name: string, type: string, path: string, extra: array<mixed>}>|null
     */
    private ?array $packages = null;

    /**
     * Adopt fixture TYPO3 extension to `typo3/testing-framework` ComposerPackageManager to allow using the composer
     * package name or extension key for functional tests as `$coreExtensionToLoad` or `$extensionToLoad`.
     *
     * Throws \RuntimeException when `typo3/testing-framework` is not installed or incompatible.
     */
    public function adoptFixtureExtensions(): void
    {
        $composerPackageManager = $this->createComposerPackageManager();
        $packageNamesAndPaths = $this->packageNamesAndPaths(['typo3-cms-framework', 'typo3-cms-extension']);
        if ($packageNamesAndPaths === []) {
            return;
        }
        if (method_exists($composerPackageManager, 'addFromPath')) {
            foreach ($packageNamesAndPaths as $packagePath) {
                $composerPackageManager->addFromPath($packagePath);
            }
            return;
        }
        if (method_exists($composerPackageManager, 'getPackageInfoWithFallback')) {
            foreach ($packageNamesAndPaths as $packageName => $packagePath) {
                $packageInfo = $composerPackageManager->getPackageInfoWithFallback($packagePath);
                if ($packageInfo === null) {
                    throw new \RuntimeException(
                        sprintf(
                            'Failed to load package info for "%s" in "%s".',
                            $packageName,
                            $packagePath
                        ),
                        1739709225
                    );
                }
                if (!($packageInfo->isSystemExtension() || $packageInfo->isExtension())) {
                    throw new \RuntimeException(
                        sprintf(
                            'Invalid package type for "%s" in "%s", must be "typo3-cms-framework" or "typo3-cms-extension", provided: "%s"',
                            $packageName,
                            $packagePath,
                            $packageInfo->getType(),
                        ),
                        1739709734
                    );
                }
            }
        }
    }

    /**
     * @param string[]|null $filterComposerTypes
     * @return array<string, array{name: string, type: string, path: string, extra: array<mixed>}>
     */
    public function packages(?array $filterComposerTypes = null): array
    {
        $this->packages ??= $this->loadPackages();
        $packages = $this->packages;
        if (!empty($filterComposerTypes)) {
            $packages = array_filter(
                $packages,
                /**
                 * @param array{name: string, type: string, path: string, extra: array<mixed>} $item
                 */
                static fn(array $item): bool => !in_array($item['type'], $filterComposerTypes, true)
            );
        }
        return $packages;
    }

    /**
     * @param string[]|null $filterComposerTypes
     * @return string[]
     */
    public function packageNames(?array $filterComposerTypes = null): array
    {
        return array_keys($this->packages($filterComposerTypes));
    }

    /**
     * @param string[]|null $filterComposerTypes
     * @return array<string, string>
     */
    public function packageNamesAndPaths(?array $filterComposerTypes = null): array
    {
        $packages = $this->packages($filterComposerTypes);
        return array_map('strval', array_column($packages, 'path', 'name'));
    }

    /**
     * @return array<string, array{name: string, type: string, path: string, extra: array<mixed>}>
     */
    private function loadPackages(): array
    {
        return $this->dataFile !== '' && file_exists($this->dataFile)
            ? include $this->dataFile
            : [];
    }

    private function createComposerPackageManager(): object
    {
        $className = $this->composerPackageManagerClassName;
        if (!class_exists($className)) {
            throw new \RuntimeException(
                sprintf(
                    'Adopting TYPO3 test fixture extensions not possible, "typo3/testing-framework" not'
                    . ' installed or invalid version. Class missing: %s',
                    $className
                ),
                1739708574
            );
        }
        return new $className();
    }
}
