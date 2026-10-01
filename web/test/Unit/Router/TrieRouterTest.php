<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Router;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Shared\Collections\StaticTrie;
use Vwork\Web\Http\HttpMethods;
use Vwork\Web\Pipeline\IPipelineHandler;
use Vwork\Web\Router\RouteMatchStatus;
use Vwork\Web\Router\TrieRouter;

/**
 * The router only reads the trie, so each test fills one by hand.
 * Handlers are stubs, looked up by name so rows can refer to them.
 */
final class TrieRouterTest extends TestCase
{
    /** path, method, handler name */
    private const array ROUTES = [
        ['/jobs', 'GET', 'list'],
        ['/jobs', 'POST', 'create'],
        ['/jobs/{id}', 'GET', 'show'],
        ['/jobs/{id}', 'DELETE', 'delete'],
        ['/', 'GET', 'home'],
    ];

    /** @var array<string, IPipelineHandler> */
    private array $handlers = [];

    private function router(): TrieRouter
    {
        /** @var StaticTrie<value-of<HttpMethods>, IPipelineHandler> $trie */
        $trie = new StaticTrie('/', static fn (string $s): ?string => preg_match('/^\{(\w+)\}$/', $s, $m) === 1 ? $m[1] : null);

        foreach (self::ROUTES as [$path, $method, $name]) {
            $this->handlers[$name] = $this->createStub(IPipelineHandler::class);
            $trie->insert($path, $method, $this->handlers[$name]);
        }

        return new TrieRouter($trie);
    }

    /**
     * @param array<string, string> $params
     */
    #[Test]
    #[TestWith([HttpMethods::GET, '/jobs', 'list', []])]
    #[TestWith([HttpMethods::POST, '/jobs', 'create', []])]
    #[TestWith([HttpMethods::GET, '/jobs/42', 'show', ['id' => '42']])]
    #[TestWith([HttpMethods::DELETE, '/jobs/42', 'delete', ['id' => '42']])]
    #[TestWith([HttpMethods::GET, '/', 'home', []])]
    public function finds_the_handler_for_the_method_and_path(HttpMethods $method, string $path, string $handler, array $params): void
    {
        $match = $this->router()->match($method, $path);

        $this->assertSame(RouteMatchStatus::Found, $match->status);
        $this->assertSame($this->handlers[$handler], $match->handler);
        $this->assertSame($params, $match->params);
    }

    #[Test]
    #[TestWith(['/billing'])]
    #[TestWith(['/jobs/42/notes'])]
    public function answers_not_found_for_an_unknown_path(string $path): void
    {
        $match = $this->router()->match(HttpMethods::GET, $path);

        $this->assertSame(RouteMatchStatus::NotFound, $match->status);
        $this->assertNull($match->handler);
    }

    /**
     * @param list<HttpMethods> $allowed
     */
    #[Test]
    #[TestWith([HttpMethods::DELETE, '/jobs', [HttpMethods::GET, HttpMethods::POST]])]
    #[TestWith([HttpMethods::POST, '/jobs/42', [HttpMethods::GET, HttpMethods::DELETE]])]
    #[TestWith([HttpMethods::HEAD, '/', [HttpMethods::GET]])] // HEAD only exists if the factory added it
    public function answers_not_allowed_with_every_method_the_path_accepts(HttpMethods $method, string $path, array $allowed): void
    {
        $match = $this->router()->match($method, $path);

        $this->assertSame(RouteMatchStatus::NotAllowed, $match->status);
        $this->assertSame($allowed, $match->allowed);
    }
}
