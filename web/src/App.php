<?php

declare(strict_types=1);

namespace Vwork\Web;

use Vwork\Web\Http\HttpStatus;
use Vwork\Web\Http\Request;
use Vwork\Web\Http\Response;
use Vwork\Web\Router\IRouter;
use Vwork\Web\Router\RouteMatchStatus;

/**
 * The whole web app, as one callable.
 *
 * One request in, one response out.
 *
 * The router finds the route. If there is one, its pipeline runs, with
 * the path params (like {id}) as the starting attributes. If not, we
 * answer 404 or 405 ourselves.
 *
 * FrankenPHP calls it once per request: frankenphp_handle_request($app).
 * Everything heavy (routes, pipelines, services) was built before the
 * first call, so each call only reads the request and answers it.
 *
 * @author Senira <senirahan@gmail.com>
 */
final readonly class App
{
    public function __construct(
        private IRouter $router,
        private bool $secure // HTTPS only
    ) {
    }

    /**
     * The part that doesn't touch globals, so tests can call it directly.
     */
    public function handleRequest(Request $request): Response
    {
        $match = $this->router->match($request->method, $request->path);

        return match ($match->status) {
            RouteMatchStatus::Found => ($match->handler ?? throw new WebError('Found route has no handler'))->handle($request, $match->params),
            RouteMatchStatus::NotAllowed => Response::methodNotAllowed($match->allowed),
            RouteMatchStatus::NotFound => Response::error(HttpStatus::NotFound),
        };
    }

    /**
     * 🔴 ⚠️ Reads PHP's globals and writes the response. ⚠️ 🔴
     */
    public function __invoke(): void
    {
        $request = Request::fromGlobals();
        $response = $this->handleRequest($request);
        $response->send($this->secure);
    }
}
