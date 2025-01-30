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

namespace SBUERK\TestFixtureExtensionAdopter\Plugin;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Package\RootPackageInterface;
use Composer\Util\Filesystem;

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
            'fixture-extension-paths' => [],
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
        if (is_array($config['typo3/testing-framework'] ?? null)) {
            foreach ($config['typo3/testing-framework'] as $key => $value) {
                if ($key === 'fixture-extension-paths' && is_array($value)) {
                    $this->config['fixture-extension-paths'] = $value;
                }
            }
        }
        return $this;
    }

    /**
     * @param int-mask-of<self::FLAG_*> $flags
     * @return string[]
     */
    public function fixtureExtensionPaths(int $flags = self::FLAG_PATHS_DEFAULT): array
    {
        return $this->get('fixture-extension-paths', $flags);
    }

    /**
     * Returns a setting
     *
     * @param int-mask-of<self::FLAG_*> $flags See class FLAG_* constants.
     * @return ($key is 'fixture-extension-paths' ? string[] : null)
     */
    public function get(string $key, int $flags = self::FLAG_PATHS_DEFAULT)
    {
        switch ($key) {
            case 'fixture-extension-paths':
                /** @var string[] $paths */
                $paths = $this->config['fixture-extension-paths'] ?? [];
                return $this->processFixtureExtensionPaths($paths, $flags);
            default:
                return null;
        }
    }

    /**
     * @param int-mask-of<self::FLAG_*> $flags
     *
     * @return array{}|array{config: non-empty-array<string, array<string>|null>}
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
     * @param string[] $paths
     * @param int-mask-of<self::FLAG_*> $flags
     *
     * @return string[]
     */
    protected function processFixtureExtensionPaths(array $paths, int $flags = 0): array
    {
        $relativePaths = $this->isRelativePathsFlag($flags);
        $paths = array_values($paths);
        array_walk($paths, fn(string $value): string => rtrim(($relativePaths ? $value : $this->realpath($value)), '/\\'));
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
        if (!isset($rootPackageExtraConfig['typo3/testing-framework'])
            || !is_array($rootPackageExtraConfig['typo3/testing-framework'])
            || !isset($rootPackageExtraConfig['typo3/testing-framework']['fixture-extension-paths'])
        ) {
            return $rootPackageExtraConfig;
        }
        if (!is_array($rootPackageExtraConfig['typo3/testing-framework']['fixture-extension-paths'])) {
            $io->writeError(sprintf(
                '<warning>extra->typo3/testing-framework/fixture-extension-paths must be an array, "%s" given.</warning>',
                gettype($rootPackageExtraConfig['typo3/testing-framework']['fixture-extension-paths'])
            ));
            unset($rootPackageExtraConfig['typo3/testing-framework']['fixture-extension-paths']);
            return $rootPackageExtraConfig;
        }
        $fixtureExtensionPaths = $rootPackageExtraConfig['typo3/testing-framework']['fixture-extension-paths'];
        if (empty($fixtureExtensionPaths)) {
            return $rootPackageExtraConfig;
        }
        $basePath = '/fake/root';
        $config = new self($basePath);
        $validPaths = [];
        foreach ($fixtureExtensionPaths as $path) {
            if (!is_string($path)) {
                continue;
            }
            $normalizedPath = $config->normalizePath($path);
            $realPath = $config->normalizePath($config->realpath($normalizedPath));
            if (!str_starts_with($realPath, $basePath . '/')) {
                $io->writeError(sprintf(
                    '<warning>Test fixture extension path must be a subdirectory of Composer root directory. Skip "%s".</warning>',
                    $path
                ));
                continue;
            }
            $validPaths[] = $path;
        }
        $rootPackageExtraConfig['typo3/testing-framework']['fixture-extension-paths'] = $validPaths;
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
