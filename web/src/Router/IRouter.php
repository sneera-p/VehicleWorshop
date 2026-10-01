<?php

declare(strict_types=1);

namespace Vwork\Web\Router;

use Vwork\Web\Http\HttpMethods;

/**
 * Knows every route, and finds the one a request is asking for.
 *
 * Routes are added once, at boot. After that, each request only asks
 * match(): "is there a route for this method and path?"
 *
 * @author Senira <senirahan@gmail.com>
 */
interface IRouter
{
    /**
     * Finds the route for this request. There are three answers:
     *
     * - Found: a route matches. It comes with the pipeline to run and the values taken from the path, like {id}.
     * - NotAllowed: the path exists, but not for this method. It comes with the methods the path does accept.
     * - NotFound: no route has this path.
     */
    public function match(HttpMethods $method, string $path): RouteMatch;
}
