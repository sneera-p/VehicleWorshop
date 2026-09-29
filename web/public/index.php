<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Vwork\Domain\IDomainRegistry;
use Vwork\Web\Http\HttpMethods;
use Vwork\Web\Controllers\IController;
use Vwork\Web\Middleware\IMiddleware;
use Vwork\Web\AppBuilder;

/**
 * @var array<class-string, Closure(IDomainRegistry): object>
 */
$controllers = require __DIR__ . '/../config/services/controllers.php';

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
 */
$routes = require __DIR__ . '/../config/routes/dummy.php';


$app = new AppBuilder()
    ->addControllers($controllers)
    ->addRoutes($routes)
    ->build();

// FrankenPHP sets FRANKENPHP_WORKER on $_SERVER only when it runs this
// file as a worker (the `worker` directive in the Caddyfile). Then the
// app is built once and serves requests in a loop. In classic mode this
// file runs once per request, so answer that one request and stop.
if (($_SERVER['FRANKENPHP_WORKER'] ?? false) && function_exists('frankenphp_handle_request')) {
    while (frankenphp_handle_request($app)) {
        gc_collect_cycles();
    }
} else {
    $app();
}
