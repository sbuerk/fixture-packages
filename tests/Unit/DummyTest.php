<?php

declare(strict_types=1);

namespace SBUERK\TestFixtureExtensionAdopter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SBUERK\TestFixtureExtensionAdopter\Dummy;

final class DummyTest extends TestCase
{
    /**
     * @test
     */
    public function dummyInstanceCanBeCreatedUsingNewKeyWord(): void
    {
        self::assertIsObject(new Dummy());
    }
}