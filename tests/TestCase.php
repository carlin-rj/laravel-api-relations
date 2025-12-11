<?php

declare(strict_types=1);

namespace Carlin\LaravelApiRelations\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
    }
}
