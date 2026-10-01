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
 * @author Senira <senirahan@gmail.com>
 */
final class TrieRouter implements IRouter
{
    /**
     * @param StaticTrie<value-of<HttpMethods>, IPipelineHandler> $trie
     */
    public function __construct(
        private readonly StaticTrie $trie
    ) {
    }

    #[Override]
    public function match(HttpMethods $method, string $path): RouteMatch
    {
        [$routes, $params] = $this->trie->search($path);

        if ($routes === []) {
            return RouteMatch::notFound();
        }

        return isset($routes[$method->value])
            ? RouteMatch::found($routes[$method->value], $params)
            : RouteMatch::notAllowed(array_map(HttpMethods::from(...), array_keys($routes)));

    }
}
