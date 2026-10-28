<?php

declare(strict_types=1);

use Vwork\Web\Controllers\DummyController;
use Vwork\Web\Controllers\IController;
use Vwork\Web\Http\HttpMethods;
use Vwork\Web\Middleware\IMiddleware;

/**
 * @var list<array{
 *  method: HttpMethods,
 *  path: string,
 *  controller: array{
 *    class: class-string<IController>,
 *    method: string
 *  },
 *  middleware: list<class-string<IMiddleware>>,
 *  context: array<string, mixed>
 * }>
 *
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
    [
        'method' => HttpMethods::GET,
        'path' => '/dummy',
        'controller' => ['class' => DummyController::class, 'method' => 'dummy'],
        'middleware' => [],
        'context' => [],
    ],
    [
        'method' => HttpMethods::GET,
        'path' => '/dummy/hello',
        'controller' => ['class' => DummyController::class, 'method' => 'dummyhello'],
        'middleware' => [],
        'context' => [],
    ],
    [
        'method' => HttpMethods::GET,
        'path' => '/dummy/greeting',
        'controller' => ['class' => DummyController::class, 'method' => 'greeting'],
        'middleware' => [],
        'context' => [],
    ]
];
