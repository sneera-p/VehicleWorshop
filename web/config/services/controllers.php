<?php

declare(strict_types=1);

use Vwork\Domain\IDomainRegistry;
use Vwork\Web\Controllers\DummyController;

/**
 * Every controller the app can route to, and how to build it.
 * Each is built once, the first time a route needs it.
 */
return [
    DummyController::class => static fn (IDomainRegistry $registry): DummyController => new DummyController(),
];
