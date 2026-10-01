<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Router;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Shared\Exception\VworkError;
use Vwork\Web\Http\HttpMethods;
use Vwork\Web\Pipeline\IPipelineHandler;
use Vwork\Web\Router\RouteMatchStatus;
use Vwork\Web\Router\RouterFactory;
use Vwork\Web\Router\RouterTypes;
use Vwork\Web\Router\TrieRouter;
use Vwork\Web\WebError;

final class RouterFactoryTest extends TestCase
{
    /**
     * @param list<array{string, string}> $routes method, path
     */
    private function factory(array $routes): RouterFactory
    {
        return new RouterFactory(array_map(
            fn (array $r): array => [
                'method' => HttpMethods::from($r[0]),
                'path' => $r[1],
                'handler' => $this->createStub(IPipelineHandler::class),
            ],
            $routes,
        ));
    }

    #[Test]
    public function create_trie_builds_a_trie_router(): void
    {
        $this->assertInstanceOf(TrieRouter::class, $this->factory([])->create(RouterTypes::Trie));
    }

    #[Test]
    #[TestWith([RouterTypes::HashTable])]  // not implemented yet
    public function create_throws_for_not_implemented(RouterTypes $type): void
    {
        $this->expectException(WebError::class);
        $this->factory([])->create($type);
    }

    #[Test]
    #[TestWith(['/jobs', '/jobs'])]
    #[TestWith(['/jobs/{id}', '/jobs/42'])]
    #[TestWith(['/', '/'])]
    public function every_get_route_also_answers_head_with_the_same_handler(string $route, string $path): void
    {
        $router = $this->factory([['GET', $route]])->createTrieRouter();

        $get = $router->match(HttpMethods::GET, $path);
        $head = $router->match(HttpMethods::HEAD, $path);

        $this->assertSame(RouteMatchStatus::Found, $head->status);
        $this->assertSame($get->handler, $head->handler);
        $this->assertSame($get->params, $head->params);
    }

    /**
     * @param list<array{string, string}> $routes
     * @param list<HttpMethods> $allowed
     */
    #[Test]
    #[TestWith([[['GET', '/jobs'], ['POST', '/jobs']], [HttpMethods::GET, HttpMethods::HEAD, HttpMethods::POST]])]
    #[TestWith([[['POST', '/jobs']], [HttpMethods::POST]])] // no GET, so no HEAD
    public function allow_lists_head_wherever_get_is_registered(array $routes, array $allowed): void
    {
        $match = $this->factory($routes)->createTrieRouter()->match(HttpMethods::DELETE, '/jobs');

        $this->assertSame($allowed, $match->allowed);
    }

    #[Test]
    public function braces_mark_a_path_param(): void
    {
        $match = $this->factory([['GET', '/staff/{staff}/jobs/{job}']])->createTrieRouter()
            ->match(HttpMethods::GET, '/staff/7/jobs/42');

        $this->assertSame(['staff' => '7', 'job' => '42'], $match->params);
    }

    /**
     * @param list<array{string, string}> $routes
     */
    #[Test]
    #[TestWith([[['GET', '/jobs'], ['GET', '/jobs']]])]              // same method, same path
    #[TestWith([[['GET', '/jobs'], ['GET', '/jobs/']]])]             // same path once empty segments go
    #[TestWith([[['GET', '/jobs'], ['HEAD', '/jobs']]])]             // HEAD is already GET's
    #[TestWith([[['GET', '/jobs/{id}'], ['POST', '/jobs/{jobId}']]])] // two names for one position
    public function conflicting_routes_fail_at_boot(array $routes): void
    {
        $this->expectException(VworkError::class);
        $this->factory($routes)->createTrieRouter();
    }
}
