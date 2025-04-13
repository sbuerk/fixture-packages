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

namespace SBUERK\FixturePackages\Plugin;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Package\RootPackageInterface;
use Composer\Util\Filesystem;

/**
 * Handles and stores plugin related configuration.
 */
final class Config
{
    public const FLAG_PATHS_DEFAULT = 0;
    public const FLAG_PATHS_RELATIVE = 1;

    /**
     * @var array<int|string, mixed>
     */
    protected array $config;
    protected string $baseDir;

    private static ?self $instance = null;

    public function __construct(
        string $baseDir
    ) {
        $this->baseDir = $this->normalizePath($baseDir);
        $this->config = [
            'paths' => [],
        ];
    }

    /**
     * Merges new config values with the existing ones (overriding)
     *
     * @param array<int|string, mixed> $config
     */
    public function merge(array $config): self
    {
        // Override defaults with given config
        if (is_array($config['sbuerk/fixture-packages'] ?? null)) {
            foreach ($config['sbuerk/fixture-packages'] as $key => $value) {
                if ($key === 'paths' && is_array($value)) {
                    $this->config['paths'] = $value;
                }
            }
        }
        return $this;
    }

    public function baseDir(): string
    {
        return $this->baseDir;
    }

    /**
     * @param int-mask-of<self::FLAG_*> $flags
     * @return array<string, string[]>
     */
    public function paths(int $flags = self::FLAG_PATHS_DEFAULT): array
    {
        return $this->get('paths', $flags);
    }

    /**
     * Returns a setting
     *
     * @param int-mask-of<self::FLAG_*> $flags See class FLAG_* constants.
     * @return ($key is 'paths' ? array<string, string[]> : null)
     */
    public function get(string $key, int $flags = self::FLAG_PATHS_DEFAULT)
    {
        switch ($key) {
            case 'paths':
                /** @var array<string, string[]> $paths */
                $paths = $this->config['paths'] ?? [];
                return $this->processFixtureExtensionPaths($paths, $flags);
            default:
                return null;
        }
    }

    /**
     * @param int-mask-of<self::FLAG_*> $flags
     *
     * @return array{}|array{config: non-empty-array<string, array<string, array<string>>|null>}
     */
    public function all(int $flags = self::FLAG_PATHS_DEFAULT)
    {
        $all = [];
        foreach (array_keys($this->config) as $key) {
            $all['config'][(string)$key] = $this->get((string)$key, $flags);
        }
        return $all;
    }

    /**
     * @return array{config: array<int|string, mixed>}
     */
    public function raw(): array
    {
        return [
            'config' => $this->config,
        ];
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->config);
    }

    /**
     * @param array<string, string[]> $paths
     * @param int-mask-of<self::FLAG_*> $flags
     *
     * @return array<string, string[]>
     */
    protected function processFixtureExtensionPaths(array $paths, int $flags = 0): array
    {
        $relativePaths = $this->isRelativePathsFlag($flags);
        $returnPaths = [];
        foreach ($paths as $path => $selection) {
            $path = rtrim(($relativePaths ? $path : $this->realpath($path)), '/\\');
            $returnPaths[$path] = $selection;
        }
        return $paths;
    }

    /**
     * @param int-mask-of<self::FLAG_*> $flags
     *
     * @return bool
     */
    private function isRelativePathsFlag(int $flags): bool
    {
        return ($flags & self::FLAG_PATHS_RELATIVE) === 1;
    }

    /**
     * Turns relative paths in absolute paths without realpath()
     *
     * Since the dirs might not exist yet we can not call realpath or it will fail.
     */
    private function realpath(string $path): string
    {
        if ($path === '') {
            return $this->baseDir;
        }
        $path = $this->normalizePath($path);
        if ($path[0] === '/' || (!empty($path[1]) && $path[1] === ':')) {
            return $path;
        }

        return $this->baseDir . '/' . $path;
    }

    private function normalizePath(string $path): string
    {
        $path = (new Filesystem())->normalizePath($path);
        if ($path === '') {
            return $path;
        }
        if ($path === '/' || rtrim($path, '/') === '') {
            return '/';
        }
        return rtrim($path, '/');
    }

    public static function load(Composer $composer, ?IOInterface $io = null): self
    {
        if (self::$instance === null) {
            $io = $io ?? new NullIO();
            $baseDir = self::extractBaseDir($composer->getConfig());
            self::$instance = (new self($baseDir))->merge(self::handleRootPackageExtraConfig($io, $composer->getPackage()));
        }
        return self::$instance;
    }

    /**
     * @param IOInterface $io
     * @param RootPackageInterface $rootPackage
     * @return array<int|string, mixed>
     */
    private static function handleRootPackageExtraConfig(IOInterface $io, RootPackageInterface $rootPackage): array
    {
        $rootPackageExtraConfig = $rootPackage->getExtra();
        if (!isset($rootPackageExtraConfig['sbuerk/fixture-packages'])
            || !is_array($rootPackageExtraConfig['sbuerk/fixture-packages'])
            || !isset($rootPackageExtraConfig['sbuerk/fixture-packages']['paths'])
        ) {
            return $rootPackageExtraConfig;
        }
        if (!is_array($rootPackageExtraConfig['sbuerk/fixture-packages']['paths'])) {
            $io->writeError(sprintf(
                '<warning>extra->sbuerk/fixture-packages/paths must be an array, "%s" given.</warning>',
                gettype($rootPackageExtraConfig['sbuerk/fixture-packages']['paths'])
            ));
            unset($rootPackageExtraConfig['sbuerk/fixture-packages']['paths']);
            return $rootPackageExtraConfig;
        }
        $fixtureExtensionPaths = $rootPackageExtraConfig['sbuerk/fixture-packages']['paths'];
        if (empty($fixtureExtensionPaths)) {
            return $rootPackageExtraConfig;
        }
        $basePath = '/fake/root';
        $config = new self($basePath);
        $validPaths = [];
        foreach ($fixtureExtensionPaths as $path => $selection) {
            if (!is_array($selection)) {
                $path = $selection;
                $selection = [
                    'autoload',
                ];
            }
            if (!is_string($path) || $path === '') {
                continue;
            }
            $selection = array_values($selection);
            array_walk($selection, function (&$value): void {
                if (is_string($value) || (is_object($value) && $value instanceof \Stringable)) {
                    $value = strtolower((string)$value);
                }
            });
            $selection = array_filter($selection, fn($value) => in_array($value, ['autoload', 'autoload-dev']));
            if ($selection === []) {
                $io->write(sprintf(
                    '<notice>No adopt mode selected for "%s" which means that none will be adopted, but package still taken as fixture package.".</notice>',
                    $path,
                ), true, $io::VERBOSE);
                continue;
            }
            $validPaths[$path] = $selection;
        }
        $rootPackageExtraConfig['sbuerk/fixture-packages']['paths'] = $validPaths;
        return $rootPackageExtraConfig;
    }

    /**
     * @param \Composer\Config $config
     * @return non-empty-string
     */
    protected static function extractBaseDir(\Composer\Config $config)
    {
        $reflectionClass = new \ReflectionClass($config);
        $reflectionProperty = $reflectionClass->getProperty('baseDir');
        $reflectionProperty->setAccessible(true);
        /** @var non-empty-string $value */
        $value = $reflectionProperty->getValue($config);
        return $value;
    }
}
