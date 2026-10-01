<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Vwork\Web\Http\HttpMethods;
use Vwork\Web\Controllers\IController;
use Vwork\Web\Middleware\IMiddleware;
use Vwork\Web\AppBuilder;
use Vwork\Web\Registry\IAppRegistry;
use Vwork\Web\Router\RouterTypes;
use Vwork\Web\Utils\IUtility;

$builder = new AppBuilder();

/**
 * @var array<class-string<IUtility>, Closure(IAppRegistry): object>
 */
$utils = require __DIR__ . '/../config/services/utils.php';
$builder->addUtils($utils);

/**
 * @var array<class-string<IController>, Closure(IAppRegistry): object>
 */
$controllers = require __DIR__ . '/../config/services/controllers.php';
$builder->addControllers($controllers);

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
$builder->addRoutes($routes);

$app = $builder
    ->withRouter(RouterTypes::Trie)
    ->withoutSecure() // REMOVE this when putting to PROD
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
