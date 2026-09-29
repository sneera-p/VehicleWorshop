<?php

declare(strict_types=1);

namespace Vwork\Web\Router;

use Vwork\Web\Http\HttpMethods;
use Vwork\Web\Pipeline\IPipelineHandler;

/**
 * What the router found for one request. It's one of three things:
 * a route (with its handler and path params), no route at all, or a
 * route that exists but not for this method.
 */
final readonly class RouteMatch
{
    /**
     * Private, so only the three named constructors below can build one.
     * That way a Found match always has a handler.
     *
     * @param array<string, string> $params values from the path, like {id}
     * @param list<HttpMethods> $allowed methods the path does accept (NotAllowed only)
     */
    private function __construct(
        public RouteMatchStatus $status,
        public ?IPipelineHandler $handler = null,
        public array $params = [],
        public array $allowed = [],
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public static function found(IPipelineHandler $handler, array $params): self
    {
        return new self(RouteMatchStatus::Found, $handler, $params);
    }

    public static function notFound(): self
    {
        return new self(RouteMatchStatus::NotFound);
    }

    /**
     * @param list<HttpMethods> $allowed
     */
    public static function notAllowed(array $allowed): self
    {
        return new self(RouteMatchStatus::NotAllowed, allowed: $allowed);
    }
}
