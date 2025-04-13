<?php

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

namespace SBUERK\FixturePackages\Plugin;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackage;
use Composer\Repository\RepositoryFactory;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Util\Filesystem;
use SBUERK\FixturePackages\Composer\Repository\FixturePathRepository;
use SBUERK\FixturePackages\Plugin\Util\AutoloadMerger;

/**
 * Provides functionalities dealing with configured test fixture packages.
 */
final class FixturePackagesService
{
    /**
     * Adopt all configured autoload namespaces from valid fixture test package
     * as autoload-namespaces for the rootPackage, prefixing namespace paths to
     * ensure working `composer dump-autoload`.
     */
    public function adopt(Composer $composer, IOInterface $io, bool $isDevMode): void
    {
        if (!$isDevMode) {
            // Only adopt namespace in development mode.
            return;
        }
        /** @var PackageInterface[] $adoptedPackages */
        $adoptedPackages = [];
        $config = Config::load($composer, $io);
        $autoloadMerger = new AutoloadMerger();
        foreach ($this->getTestFixturePackages($config, $composer, $io, []) as $package) {
            if ($this->isPackageInstalled($composer, $package)) {
                $io->write(
                    sprintf(
                        '<warning>>> Skipping autoload adopt for package "%s" in "%s", already installed.</warning>',
                        $package->getName(),
                        $package->getDistUrl()
                    )
                );
                continue;
            }
            $autoloadMerger->mergeToAutoloadDev($io, $composer->getPackage(), $package);
            $adoptedPackages[] = $package;
        }
        $this->writeFixturesPackagesStateFile($io, $config, $composer, ...$adoptedPackages);
    }

    /**
     * @param Config $config
     * @param Composer $composer
     * @param IOInterface $io
     * @param string[] $allowedPackageTypes
     * @return PackageInterface[]
     */
    private function getTestFixturePackages(Config $config, Composer $composer, IOInterface $io, array $allowedPackageTypes): array
    {
        $repositoryManager = $this->createPreparedRepositoryManager($config, $composer, $io);
        /** @var PackageInterface[] $packages */
        $packages = [];
        foreach ($repositoryManager->getRepositories() as $repository) {
            foreach ($repository->getPackages() as $package) {
                if ($allowedPackageTypes !== [] && !in_array($package->getType(), $allowedPackageTypes, true)) {
                    continue;
                }
                $packages[] = $package;
            }
        }
        return $packages;
    }

    /**
     * Creates a dedictead {@see RepositoryManager} instance and prepare it
     * only with {@see FixturePathRepository} repositories defined in the
     * {@see RootPackage::getExtra()} array.
     */
    private function createPreparedRepositoryManager(Config $config, Composer $composer, IOInterface $io): RepositoryManager
    {
        $repositoryManager = $this->createRepositoryManager($io, $composer);
        foreach ($config->paths(Config::FLAG_PATHS_RELATIVE) as $path => $selection) {
            // @todo pass down selections ?
            $repositoryManager->addRepository($this->createRepositoryForPath($repositoryManager, $composer, $io, $path, $selection));
        }
        return $repositoryManager;
    }

    /**
     * Create fixture-path repository for given path.
     *
     * @param string[] $selection
     */
    private function createRepositoryForPath(RepositoryManager $repositoryManager, Composer $composer, IOInterface $io, string $path, array $selection): RepositoryInterface
    {
        return RepositoryFactory::fromString(
            $io,
            $composer->getConfig(),
            JsonFile::encode([
                'type' => 'fixture-path',
                'url' => $path,
                'selection' => $selection,
            ]),
            true,
            $repositoryManager
        );
    }

    /**
     * For test-fixture package repository we do not need to support all repository types, registered into a
     * {@see RepositoryManager} instance using {@see RepositoryFactory::manager()} factory method. That help
     * to avoid unneeded repository types and use customized path repository to avoid duplicated packages
     * based on feature branch detection.
     *
     * {@see FixturePathRepository}.
     */
    private function createRepositoryManager(IOInterface $io, Composer $composer): RepositoryManager
    {
        $repositoryManager = RepositoryFactory::manager($io, $composer->getConfig());
        $repositoryManager->setRepositoryClass(
            'fixture-path',
            'SBUERK\FixturePackages\Composer\Repository\FixturePathRepository'
        );
        return $repositoryManager;
    }

    /**
     * Determine if $package is already installed, required using a casual
     * path repository for example and added as require or require-dev.
     */
    private function isPackageInstalled(Composer $composer, PackageInterface $package): bool
    {
        $allRequiredPackageNames = [
            ...array_keys($composer->getPackage()->getRequires()),
            ...array_keys($composer->getPackage()->getDevRequires()),
        ];
        return in_array($package->getName(), $allRequiredPackageNames, true);
    }

    private function writeFixturesPackagesStateFile(IOInterface $io, Config $pluginConfig, Composer $composer, PackageInterface ...$packages): void
    {
        $filesystem = new Filesystem();
        $vendorPath = $filesystem->normalizePath($composer->getConfig()->get('vendor-dir'));
        $dataPath = $vendorPath . '/sbuerk';
        $fixturePackagesFile = $dataPath . '/fixture-packages.php';
        $data = $this->createExportPackagesArray($pluginConfig, $dataPath, ...$packages);
        $filesystem->ensureDirectoryExists($dataPath);
        $filesystem->filePutContentsIfModified(
            $fixturePackagesFile,
            '<?php return ' . $this->dumpToPhpCode($data) . ';' . "\n"
        );
        if (!file_exists(__DIR__ . '/../../tmpl/AvailableFixturePackages.php')) {
            $io->writeError('>> [sbuerk/fixture-packages] Could not find AvailableFixturePackages class template.');
            return;
        }
        if (file_exists($dataPath . '/AvailableFixturePackages.php')
            && hash_file('sha256', $dataPath . '/AvailableFixturePackages.php') !== hash_file('sha256', __DIR__ . '/../../tmpl/AvailableFixturePackages.php')
        ) {
            $filesystem->unlink($dataPath . '/AvailableFixturePackages.php');
            $io->info('>> [sbuerk/fixture-packages] AvailableFixturePackages.php exists, but content changed. Remove it.');
        }
        if (!file_exists($dataPath . '/AvailableFixturePackages.php')) {
            $io->info('>> [sbuerk/fixture-packages] Provide AvailableFixturePackages.php ');
            $filesystem->copy(__DIR__ . '/../../tmpl/AvailableFixturePackages.php', $dataPath . '/AvailableFixturePackages.php');
        }
        $rootPackage = $composer->getPackage();
        $devAutoload = $rootPackage->getDevAutoload();
        if (!isset($devAutoload['classmap']) || !is_array($devAutoload['classmap'])) {
            $devAutoload['classmap'] = [];
        }
        // Add AvailableFixturePackages class to root package autoload-dev
        $classFile = $composer->getConfig()->get('vendor-dir', \Composer\Config::RELATIVE_PATHS) . '/sbuerk/AvailableFixturePackages.php';
        $devAutoload['classmap'][] = $classFile;
        $rootPackage->setDevAutoload($devAutoload);
    }

    /**
     * @param PackageInterface ...$packages
     * @return array<string, array{name: string, type: string, path: string, extra: array<mixed>}>
     */
    private function createExportPackagesArray(Config $pluginConfig, string $dataPath, PackageInterface ...$packages): array
    {
        $filesystem = new Filesystem();
        $exportPackages = [];
        foreach ($packages as $package) {
            $relativePath = $filesystem->findShortestPath(
                $dataPath,
                $pluginConfig->baseDir() . '/' . rtrim((string)$package->getDistUrl(), '/'),
                true,
                true
            );
            $exportPackages[$package->getName()] = [
                'name' => $package->getName(),
                'type' => $package->getType(),
                'path' => $relativePath,
                'extra' => $package->getExtra(),
            ];
        }
        return $exportPackages;
    }

    /**
     * @param array<int|string, mixed> $array
     */
    private function dumpToPhpCode(array $array = [], int $level = 0): string
    {
        $lines = "array(\n";
        $level++;

        foreach ($array as $key => $value) {
            $lines .= str_repeat('    ', $level);
            $lines .= is_int($key) ? $key . ' => ' : var_export($key, true) . ' => ';

            if (is_array($value)) {
                if (!empty($value)) {
                    $lines .= $this->dumpToPhpCode($value, $level);
                } else {
                    $lines .= "array(),\n";
                }
            } elseif ($key === 'path' && is_string($value)) {
                if ((new Filesystem())->isAbsolutePath($value)) {
                    $lines .= var_export($value, true) . ",\n";
                } else {
                    $lines .= '__DIR__ . ' . var_export('/' . $value, true) . ",\n";
                }
            } elseif (is_string($value)) {
                $lines .= var_export($value, true) . ",\n";
            } elseif (is_bool($value)) {
                $lines .= ($value ? 'true' : 'false') . ",\n";
            } elseif (is_null($value)) {
                $lines .= "null,\n";
            } else {
                throw new \UnexpectedValueException('Unexpected type ' . gettype($value));
            }
        }

        $lines .= str_repeat('    ', $level - 1) . ')' . ($level - 1 === 0 ? '' : ",\n");

        return $lines;
    }
}
