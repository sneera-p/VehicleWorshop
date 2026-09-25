<?php

declare(strict_types=1);

namespace Vwork\Web\Router;

use Override;
use Vwork\Shared\Collections\StaticTrie;
use Vwork\Web\Http\HttpMethods;
use Vwork\Web\Pipeline\IPipelineHandler;

/**
 * Stores every route in a trie, keyed by path.
 *
 * One path can hold several routes, one per method (GET /jobs and
 * POST /jobs), so each trie node keeps a list of method + pipeline pairs.
 *
 * Path params are written {name}, like /jobs/{id}. The value found
 * there comes back in RouteMatch::$params['id'].
 *
 * @phpstan-type Route array{method: HttpMethods, handler: IPipelineHandler}
 *
 * @author Senira <senirahan@gmail.com>
 */
final class Router implements IRouter
{
    /** @var StaticTrie<Route> */
    private StaticTrie $trie;

    public function __construct()
    {
        /** @var StaticTrie<Route> $trie */
        $trie = new StaticTrie(
            separator: '/',
            // The same method twice on one path is a config mistake: fail at boot.
            isDuplicate: static fn (array $a, array $b): bool => $a['method'] === $b['method'],
            // "{id}" is a param named "id". Anything else is a fixed segment.
            wildcard: static fn (string $segment): ?string => preg_match('/^\{(\w+)\}$/', $segment, $m) === 1 ? $m[1] : null,
        );

        $this->trie = $trie;
    }

    #[Override]
    public function register(HttpMethods $method, string $path, IPipelineHandler $handler): void
    {
        $this->trie->insert($path, ['method' => $method, 'handler' => $handler]);
    }

    #[Override]
    public function match(HttpMethods $method, string $path): RouteMatch
    {
        // The first request locks the trie. Registering after this throws.
        if (!$this->trie->isBuilt) {
            $this->trie->build();
        }

        ['values' => $routes, 'params' => $params] = $this->trie->search($path);

        if ($routes === []) {
            return RouteMatch::notFound();
        }

        foreach ($routes as $route) {
            if ($route['method'] === $method) {
                return RouteMatch::found($route['handler'], $params);
            }
        }

        return RouteMatch::notAllowed(array_map(
            static fn (array $route): HttpMethods => $route['method'],
            $routes,
        ));
    }
}
