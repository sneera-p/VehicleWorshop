<?php

declare(strict_types=1);

namespace Vwork\Web\Router;

use Vwork\Shared\Collections\StaticTrie;
use Vwork\Web\Http\HttpMethods;
use Vwork\Web\Pipeline\IPipelineHandler;
use Vwork\Web\WebError;

/**
 * Turns the route list into a router, once, at boot.
 *
 * Routes arrive with their pipelines already built (PipelineFactory's
 * job). This class only decides how they're stored for lookup.
 *
 * @author Senira <senirahan@gmail.com>
 */
final readonly class RouterFactory
{
    /**
     * @param list<array{
     *  method: HttpMethods,
     *  path: string,
     *  handler: IPipelineHandler
     * }> $routes
     */
    public function __construct(
        private array $routes
    ) {
    }

    /**
     * @throws WebError for an unknown or unimplemented type
     */
    public function create(RouterTypes $type): IRouter
    {
        return match ($type) {
            RouterTypes::Trie => $this->createTrieRouter(),
            RouterTypes::HashTable => $this->createHashTableRouter(),
        };
    }

    /**
     * @throws \Vwork\Shared\Exception\VworkError if two routes share a method and
     *     path, or name a wildcard differently at the same position
     */
    public function createTrieRouter(): TrieRouter
    {
        /** @var StaticTrie<value-of<HttpMethods>, IPipelineHandler> $trie */
        $trie = new StaticTrie(
            separator: '/',
            // "{id}" is a wildcard named "id". Anything else is a fixed segment.
            wildcard: static fn (string $segment): ?string => preg_match('/^\{(\w+)\}$/', $segment, $m) === 1 ? $m[1] : null,
        );

        foreach ($this->routes as ['method' => $method, 'path' => $path, 'handler' => $handler]) {
            $trie->insert($path, $method->value, $handler);

            // HEAD is GET without the body; the server drops the body for us
            if ($method === HttpMethods::GET) {
                $trie->insert($path, HttpMethods::HEAD->value, $handler);
            }
        }

        return new TrieRouter($trie);
    }

    /**
     * @throws WebError always, for now
     */
    public function createHashTableRouter(): IRouter
    {
        throw new WebError('HashTableRouter is not implemented yet');
    }
}
