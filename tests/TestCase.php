<?php

namespace Funseco\Messages\Tests;

use Funseco\Admin\Tests\Support\AbilitySnapshot;
use Funseco\Core\Testing\TestCase as CoreTestCase;
use Funseco\Guard\GuardServiceProvider;
use Funseco\Admin\AdminServiceProvider;
use Funseco\Messages\MessagesServiceProvider;

abstract class TestCase extends CoreTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AbilitySnapshot::apply();
    }

    /** Dependencies first, then this plugin (isolated runs load only these). */
    protected function plugins(): array
    {
        return [
            AdminServiceProvider::class,
            GuardServiceProvider::class,
            MessagesServiceProvider::class,
        ];
    }
}
