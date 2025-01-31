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

namespace SBUERK\FixturePackages\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SBUERK\FixturePackages\Plugin\Config;

abstract class BaseUnitTestCase extends TestCase
{
    private string $oldWorkingDirectory = '';

    public function setUp(): void
    {
        // Backup current working folder, so it can be restored in self::tearDown(). Some tests requires to change
        // the working directory, but we should restore original working directory to avoid a dirty environment.
        $this->oldWorkingDirectory = getcwd();

        parent::setUp();
    }

    public function tearDown(): void
    {
        // Restore original working directory. Required for a couple of tests, which requires to operate
        // in fixture folders, but we want to be in starting folder afterward to avoid a dirty environment.
        chdir($this->oldWorkingDirectory);

        /**
         * Reset static property {@see Config::$instance} holding instance used within {@see Config::load()}. This
         * essential, otherwise tests will fail for different scenarios.
         */
        (new \ReflectionClass(Config::class))->setStaticPropertyValue('instance', null);

        parent::tearDown();
    }

    /**
     * @param object|string $object
     * @param string $method
     * @return \ReflectionMethod
     * @throws \ReflectionException
     */
    protected function createClassMethodInvoker($object, string $method): \ReflectionMethod
    {
        if (!((is_string($object) && class_exists($object)) || is_object($object))) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Argument $object must be of type `object|string`, provided: %s',
                    gettype($object)
                ),
                1738868978
            );
        }
        if ($object instanceof \ReflectionClass) {
            $invoker = $object->getMethod($method);
        } else {
            $invoker = new \ReflectionMethod($object, $method);
        }
        if (PHP_VERSION_ID < 801000) {
            $invoker->setAccessible(true);
        }
        return $invoker;
    }

    /**
     * @throws \ReflectionException
     */
    protected function createClassReflection($object): \ReflectionClass
    {
        if (!((is_string($object) && class_exists($object)) || is_object($object))) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Argument $object must be of type `object|string`, provided: %s',
                    gettype($object)
                ),
                1738868978
            );
        }
        return new \ReflectionClass($object);
    }
}
