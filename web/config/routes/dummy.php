<?php

declare(strict_types=1);

use Vwork\Web\Controllers\DummyController;
use Vwork\Web\Http\HttpMethods;

/**
 * Sample route. No middleware, so it needs no context.
 */
return [
    [
        'method' => HttpMethods::GET,
        'path' => '/hello',
        'controller' => ['class' => DummyController::class, 'method' => 'hello'],
        'middleware' => [],
        'context' => [],
    ],
];
