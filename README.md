# Composer plugin `sbuerk/fixture-packages`

> [!IMPORTANT]
> **EXPERIMENTAL** for now, behaviour and configuration can and will change at
> any point in a breaking way until baseline implementation has been proven as
> battle-proof and 1.x is released.

Package `sbuerk/fixture-packages` provides a development context composer plugin,
which allows to define paths to scan for composer packages and adopt `autoload`
registrations from package as `autoload-dev` registration of the root package,
effectly removing the need to register each package autoload manually to have
autoloading in place, for example when writing unit, functional or integration
tests based on [PHPUnit](https://github.com/sebastianbergmann/phpunit).

## Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Generated files](#generated-files)
  - [Use generated `FixturePackages`](#use-generated-fixturepackages)
    - [[TYPO3] Functional testing with typo3/testing-framework](#typo3-functional-testing-with-typo3testing-framework)

## Installation

> [!NOTE]
> The plugin automatically works after the installation and updates the namespace
> registration when dumping autoloader information.

> [!TIP]
> You can enforce regeneration of autoload information, for example after adding
> or removing packages to/from one of the [configured](#Configuration) fixture
> paths by using `composer dump-autoload`. This is also use-full if autoload
> configuration for registered packages has changed, for example adding additonal
> namespaces.

> [!IMPORTANT]
> This plugin only registers fixture package autoload configuration as root package
> autoload-dev configuration when executed in `--dev (DEFAULT)` mode, doing nothing
> when `--no-dev` is used or has been used.

**Set config to allow plugin**

```shell
composer config allow-plugins.sbuerk/fixture-packages true
```

**Require composer plugin as development dependency**

> [!IMPORTANT]
> The plugin adds additional package namespaces as development dependencies to
> simplify development setups, mainly for test execution, and usually no need
> to have it in production installations and **should not be** installed as
> dependency, special for packages which are libraries, bundles, extensions,
> plugins and itself required by projects or other packages.

```shell
composer require --dev "sbuerk/fixture-packages"
```

**One-liner**

```shell
composer config allow-plugins.sbuerk/fixture-packages true && \
  composer require --dev "sbuerk/fixture-packages"
```

## Configuration

[composer](https://getcomposer.org) restricts places for custom configuration within the `composer.json`
schema to the extra-section and is used to configure paths to scan for extensions:

```json
{
  "extra": {
    "sbuerk/fixture-packages": [
      "multiple-packages-in-folder/*",
      "pattern-matching/*/matching/same/subpath-in-multiple-places/*",
      "packages/direct-package-path-containing-a-composer.json"
    ]
  }
}
```

## Generated files

This plugin create two files:

| File                               | Description                                                                |
|------------------------------------|----------------------------------------------------------------------------|
| vendor/sbuerk/fixture-packages.php | Returns an array with fixture package information.                         |
| vendor/sbuerk/FixturePackages.php  | Provides `FixturePackages` to work with fixture package information state. |

> [!NOTE]
> These files are not generated to be used casually, but provides
> a helping hand to be used to integrate it eventually into some
> framework / testing-framework, for example to allow dynamically
> load extensions, plugings, bundles or how the framework calls
> them.

### Use generated `FixturePackages`

The `FixturePackages` class provides conveniant way to work with
the data and provess additional tasks, for example using within
[typo3/testing-framework](https://github.com/typo3/testing-framework)
bases functional tests to register them and use them by composer
package name as extension to load in functional tests.

#### [TYPO3] Functional testing with typo3/testing-framework

The `FixturePackages` class provides a method to adopt fixture packages,
which are valid TYPO3 extensions into the `ComposerPackageManager` which
allows to use the extension-key or composer package name to configure the
extension to load in functional tests.

This is not done automatically yet, but can be done easily by copy & paste

```php
/**
 * Automatically add fixture extensions to the `typo3/testing-framework`
 * {@see \TYPO3\TestingFramework\Composer\ComposerPackageManager} to
 * allow composer package name or extension keys of fixture extension in
 * {@see \TYPO3\TestingFramework\Core\Functional\FunctionalTestCase::$testExtensionToLoad}.
 */
if (class_exists(\SBUERK\FixturePackages::class)) {
    (new \SBUERK\FixturePackages())->adoptFixtureExtensions();
}
```

into the `FunctionalTestBootstrap.php` file within your extension or project.

> [!TIP]
> If you do not have the functional bootstrap copied from the testing-framework,
> you should do that prior to add the snippet. Read the file header along with
> `FunctionalTests.xml` PHPUnit configuration file already stating that these
> files **should* be copied anyway.

Usually, the bootstrap file for functional tests looks similar to the following:

```php
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

/**
 * Boilerplate for a functional test phpunit boostrap file.
 *
 * This file is loosely maintained within TYPO3 testing-framework, extensions
 * are encouraged to not use it directly, but to copy it to an own place,
 * usually in parallel to a FunctionalTests.xml file.
 *
 * This file is defined in FunctionalTests.xml and called by phpunit
 * before instantiating the test suites.
 */
(static function () {

    /**
     * Automatically add fixture extensions to the `typo3/testing-framework`
     * {@see \TYPO3\TestingFramework\Composer\ComposerPackageManager} to
     * allow composer package name or extension keys of fixture extension in
     * {@see \TYPO3\TestingFramework\Core\Functional\FunctionalTestCase::$testExtensionToLoad}.
     */
    if (class_exists(\SBUERK\FixturePackages::class)) {
        (new \SBUERK\FixturePackages())->adoptFixtureExtensions();
    }

    $testbase = new \TYPO3\TestingFramework\Core\Testbase();
    $testbase->defineOriginalRootPath();
    $testbase->createDirectory(ORIGINAL_ROOT . 'typo3temp/var/tests');
    $testbase->createDirectory(ORIGINAL_ROOT . 'typo3temp/var/transient');
})();
```

Use the composer package name now in `FunctionalTestCase` based tests:

```php
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

namespace Vendor\MyExtension\Tests\Functional;

final class DummyTest extends FunctionalTestCase
{
    protected array $testExtensionToLoad = [
        'vendor/fixture-extension',
        'vendor/root-package',
    ];
}
```

instead of something like 

```php
    protected array $testExtensionToLoad = [
        __DIR__ . '/Fixtures/Extensions/fixture-extension',        
        'vendor/root-package',
    ];
```
