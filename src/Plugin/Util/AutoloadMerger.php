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

namespace SBUERK\FixturePackages\Plugin\Util;

use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Util\Filesystem;

/**
 * Provides merging {@see PackageInterface} autoload configuration to
 * {@see RootPackageInterface} autoload-dev section, prefixing merged
 * paths with relative package paths.
 */
final class AutoloadMerger
{
    /**
     * Merge all {@see PackageInterface::getAutoload()} definitions into {@see RootPackageInterface::setDevAutoload()},
     * prefixing paths with relative $package paths to ensure correct generated class autoload loader instances by the
     * `composer dump-autoload` command.
     *
     * @todo Consider to lock this to {@see PackageInterface::getDistType()} === 'fixture-path'
     * @todo Implement `files` namespace merging.
     * @todo Implement `classmap` namespace merging.
     */
    public function mergeAutoloadToAutoloadDev(
        IOInterface $io,
        RootPackageInterface $rootPackage,
        PackageInterface $package
    ): void {
        $this->mergeAllNamespaces($io, $rootPackage, $package);
    }

    /**
     * Intermediate method to merge all psr-0 and psr-4 autoload namespaces from $package to autoload of the
     * $rootPackage instance, prefixing with package relative path to ensure correct autoload loader instances
     * when using `composer dump-autoload`.
     */
    private function mergeAllNamespaces(
        IOInterface $io,
        RootPackageInterface $rootPackage,
        PackageInterface $package
    ): void {
        $this->mergePsrNamespaces($io, $rootPackage, $package, 'psr-0');
        $this->mergePsrNamespaces($io, $rootPackage, $package, 'psr-4');
        $this->mergeSimpleNamespace($io, $rootPackage, $package, 'files');
        $this->mergeSimpleNamespace($io, $rootPackage, $package, 'classmap');
    }

    /**
     * Merge all package autoload psr-o and psr-4 namespaces from `$package` to `$rootPackage` autoload-dev.
     *
     * Modifying namespace paths by prefixing $package->getSourceUrl() ensures correct path information,
     * otherwise composer dump-autoload will create invalid class autoloader instances and paths.
     */
    private function mergePsrNamespaces(
        IOInterface $io,
        RootPackageInterface $rootPackage,
        PackageInterface $package,
        string $psr
    ): void {
        if (!$this->isValidPsrNamespace($psr)) {
            return;
        }
        $autoload = $package->getAutoload();
        if ($autoload === [] || !isset($autoload[$psr]) || !is_array($autoload[$psr])) {
            return;
        }
        foreach ($autoload[$psr] as $namespace => $namespacePaths) {
            if (!is_string($namespace)) {
                continue;
            }
            if (is_string($namespacePaths)) {
                $this->mergePsrNamespace(
                    $io,
                    $rootPackage,
                    $package,
                    $psr,
                    $namespace,
                    $namespacePaths
                );
                continue;
            }
            if (is_array($namespacePaths) && $namespacePaths !== []) {
                foreach ($namespacePaths as $namespacePath) {
                    if (!is_string($namespacePath)) {
                        continue;
                    }
                    $this->mergePsrNamespace(
                        $io,
                        $rootPackage,
                        $package,
                        $psr,
                        $namespace,
                        $namespacePath
                    );
                }
            }
        }
    }

    /**
     * Merge single `$namespace` location (`$namespacePath`) for `$psr` (psr-0|psr-4) to as $target autoload-dev
     * namespace.
     *
     * If the namespace for the psr-type already exists, ensure to convert it to an array and provide both or more
     * paths for the same namespace.
     *
     * $namespacePath is prefixed with $package->getSourceUrl(), otherwise path information for composer dump-autoload
     * would be wrong.
     *
     * @todo Consider to make cross psr-type checks.
     */
    private function mergePsrNamespace(
        IOInterface $io,
        RootPackageInterface $rootPackage,
        PackageInterface $package,
        string $psr,
        string $namespace,
        string $namespacePath
    ): void {
        if (!$this->isValidPsrNamespace($psr)) {
            return;
        }
        $modifiedNamespacePath = $this->modifyPackageNamespacePath($package, $namespacePath);
        $devAutoload = $rootPackage->getDevAutoload();
        // Ensure $psr section exists
        if (!isset($devAutoload[$psr]) || !is_array($devAutoload[$psr])) {
            $devAutoload[$psr] = [];
        }
        // Does not exist yet, simply add autoload namespace as string mapping.
        if (!isset($devAutoload[$psr][$namespace])) {
            $devAutoload[$psr][$namespace] = $modifiedNamespacePath;
            /** @var array{"psr-0"?: array<string, array<string>|string>, "psr-4"?: array<string, array<string>|string>, classmap?: list<string>, files?: list<string>} $devAutoload */
            $rootPackage->setDevAutoload($devAutoload);
            $this->writeAdoptedNamespaceMessage($io, $psr, $package->getName(), $namespace, $modifiedNamespacePath);
            return;
        }
        // Namespace exists, but is string mapping. Convert to array.
        if (is_string($devAutoload[$psr][$namespace])) {
            if ($devAutoload[$psr][$namespace] === $modifiedNamespacePath) {
                // same path, skip.
                /** @var array{"psr-0"?: array<string, array<string>|string>, "psr-4"?: array<string, array<string>|string>, classmap?: list<string>, files?: list<string>} $devAutoload */
                $rootPackage->setDevAutoload($devAutoload);
                return;
            }
            $devAutoload[$psr][$namespace] = [
                $devAutoload[$psr][$namespace],
            ];
        }
        if (isset($devAutoload[$psr][$namespace])
            && is_array($devAutoload[$psr][$namespace])
            && !in_array($modifiedNamespacePath, $devAutoload[$psr][$namespace], true)
        ) {
            $devAutoload[$psr][$namespace][] = $modifiedNamespacePath;
            /** @var array{"psr-0"?: array<string, array<string>|string>, "psr-4"?: array<string, array<string>|string>, classmap?: list<string>, files?: list<string>} $devAutoload */
            $rootPackage->setDevAutoload($devAutoload);
            $this->writeAdoptedNamespaceMessage($io, $psr, $package->getName(), $namespace, $modifiedNamespacePath);
        }
    }

    /**
     * Merge package $type namespaces to $rootPackage, prefixing paths with relative $package path.
     */
    private function mergeSimpleNamespace(
        IOInterface $io,
        RootPackageInterface $rootPackage,
        PackageInterface $package,
        string $type
    ): void {
        if (!$this->isValidNamespace($type)) {
            return;
        }
        $autoload = $package->getAutoload();
        if (!isset($autoload[$type]) || !is_array($autoload[$type]) || $autoload[$type] === []) {
            $io->debug(sprintf(
                '<info>Package "%s" does not have an "%s" autoload section. Nothing to merge.</info>',
                $package->getName(),
                $type
            ));
            return;
        }
        $devAutoload = $rootPackage->getDevAutoload();
        foreach ($autoload[$type] as $namespacePath) {
            if (!is_string($namespacePath)) {
                continue;
            }
            $modifiedNamespacePath = $this->modifyPackageNamespacePath($package, $namespacePath);
            if (isset($devAutoload[$type])
                && is_array($devAutoload[$type])
                && in_array($modifiedNamespacePath, $devAutoload[$type], true)
            ) {
                $io->debug(sprintf(
                    '<info>Package"%s" namespace "%s" path "%s" already exists in root package as "%s". Skipped.</info>',
                    $package->getName(),
                    $type,
                    $namespacePath,
                    $modifiedNamespacePath
                ));
                continue;
            }
            // Ensure that $type section exists
            if (!isset($devAutoload[$type]) || !is_array($devAutoload[$type])) {
                $devAutoload[$type] = [];
            }
            $devAutoload[$type][] = $modifiedNamespacePath;
            /** @var array{"psr-0"?: array<string, array<string>|string>, "psr-4"?: array<string, array<string>|string>, classmap?: list<string>, files?: list<string>} $devAutoload */
            $rootPackage->setDevAutoload($devAutoload);
            $this->writeAdoptedNamespaceMessage(
                $io,
                $type,
                $package->getName(),
                '',
                $modifiedNamespacePath
            );
        }
    }

    /**
     * Prefix package source url (relative path) to namespace.
     */
    private function modifyPackageNamespacePath(PackageInterface $package, string $namespacePath): string
    {
        $filesystem = new Filesystem();
        return rtrim((string)$package->getSourceUrl(), '/')
            . '/'
            . $filesystem->normalizePath($namespacePath);
    }

    /**
     * Generic message writer to ensure consistent formatting of different outputs.
     */
    private function writeAdoptedNamespaceMessage(
        IOInterface $io,
        string $key,
        string $packageName,
        string $namespace,
        string $namespacePath
    ): void {
        if ($namespace === '') {
            $io->write(
                sprintf(
                    '>> [%s][%s][] = %s adopted.',
                    $packageName,
                    $key,
                    $namespacePath
                ),
                true,
                IOInterface::VERBOSE
            );
            return;
        }
        $io->write(
            sprintf(
                '>> [%s][%s][%s] = %s adopted.',
                $packageName,
                $key,
                $namespace,
                $namespacePath
            ),
            true,
            IOInterface::VERBOSE
        );
    }

    private function isValidPsrNamespace(string $psr): bool
    {
        return $psr === 'psr-0' || $psr === 'psr-4';
    }

    private function isValidNamespace(string $type): bool
    {
        return $type === 'files' || $type === 'classmap';
    }
}
