<?php

declare(strict_types=1);

use Vwork\Shared\Collections\IRegistry;
use Vwork\Web\Controllers\DummyController;
use Vwork\Web\Utils\CsrfToken;
use Vwork\Web\Utils\View;

/**
 * Every controller the app can route to, and how to build it.
 * Each is built once, the first time a route needs it.
 */
return [
    DummyController::class => static fn (IRegistry $r): DummyController => new DummyController(
        view: $r->get(View::class),
        csrf: $r->get(CsrfToken::class)
    ),
];
