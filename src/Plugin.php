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

namespace SBUERK\FixturePackages;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use SBUERK\FixturePackages\Plugin\FixturePackagesService;

/**
 * Provides the package composer plugin implementation and is the starting point.
 */
final class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var array<string, bool>
     */
    private $handledEvents = [];

    private ?FixturePackagesService $testFixtureExtensionService = null;

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::PRE_AUTOLOAD_DUMP => [
                'listen',
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $io->write(
            '<info>>> Fixture package adopter plugin activated.</info>',
            true,
            IOInterface::DEBUG
        );
        $composer->getEventDispatcher()->addSubscriber($this);
    }

    /**
     * {@inheritDoc}
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // noop - required by PluginInterface
        $io->write(
            '<info>>> Fixture package adopter plugin deactivate.</info>',
            true,
            IOInterface::DEBUG
        );
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // noop - required by PluginInterface
        $io->write(
            '<info>>> Fixture package adopter plugin uninstalled.</info>',
            true,
            IOInterface::DEBUG
        );
    }

    /**
     * Listens to Composer events.
     *
     * This method is very minimalist on purpose. We want to load the actual
     * implementation only after updating the Composer packages so that we get
     * the updated version (if available).
     *
     * @param Event $event The Composer event.
     */
    public function listen(Event $event): void
    {
        if (!empty($this->handledEvents[$event->getName()])) {
            return;
        }
        $this->handledEvents[$event->getName()] = true;
        // Plugin has been uninstalled
        if (!file_exists(__FILE__) || !file_exists(__DIR__ . '/Plugin/FixturePackagesService.php')) {
            return;
        }
        // Load the implementation only after updating Composer so that we get
        // the new version of the plugin when a new one was installed
        $this->testFixtureExtensionService = $this->testFixtureExtensionService ?? new FixturePackagesService();
        switch ($event->getName()) {
            case ScriptEvents::PRE_AUTOLOAD_DUMP:
                $this->testFixtureExtensionService->adopt(
                    $event->getComposer(),
                    $event->getIO(),
                    $event->isDevMode()
                );
                break;
        }
    }
}
