<?php

declare(strict_types=1);

namespace Vwork\Web;

use Override;
use Vwork\Web\Http\HttpStatus;
use Vwork\Web\Http\Request;
use Vwork\Web\Http\Response;
use Vwork\Web\Router\IRouter;
use Vwork\Web\Router\RouteMatchStatus;

/**
 * One request in, one response out.
 *
 * The router finds the route. If there is one, its pipeline runs, with
 * the path params (like {id}) as the starting attributes. If not, we
 * answer 404 or 405 ourselves.
 *
 * @author Senira <senirahan@gmail.com>
 */
final readonly class App implements IApp
{
    public function __construct(
        private IRouter $router,
    ) {
    }

    /**
     * 🔴 ⚠️ Reads PHP's globals and writes the response. ⚠️ 🔴
     */
    #[Override]
    public function __invoke(): void
    {
        $request = Request::fromGlobals();
        $response = $this->handleRequest($request);
        $response->send();
    }

    /**
     * The part that doesn't touch globals, so tests can call it directly.
     */
    #[Override]
    public function handleRequest(Request $request): Response
    {
        $match = $this->router->match($request->method, $request->path);

        return match ($match->status) {
            RouteMatchStatus::Found => ($match->handler ?? throw new WebError('Found route has no handler'))->handle($request, $match->params),
            RouteMatchStatus::NotAllowed => Response::methodNotAllowed($match->allowed),
            RouteMatchStatus::NotFound => Response::error(HttpStatus::NotFound),
        };
    }
}
