<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Router;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Vwork\Shared\Exception\VworkError;
use Vwork\Web\Http\HttpMethods;
use Vwork\Web\Pipeline\ControllerHandler;
use Vwork\Web\Router\RouteMatch;
use Vwork\Web\Router\Router;
use Vwork\Web\Router\RouteMatchStatus;
use Vwork\Web\Test\Stubs\ControllerStub;

/**
 * Path matching itself (fixed vs wildcard, fallback) is covered by
 * StaticTrieTest. These tests cover what the router adds on top.
 */
final class RouterTest extends TestCase
{
    private static function handler(): ControllerHandler
    {
        return new ControllerHandler(new ControllerStub(), 'index');
    }

    #[Test]
    public function match_finds_the_handler_for_the_method_with_params(): void
    {
        $get = self::handler();
        $post = self::handler();
        $router = new Router();
        $router->register(HttpMethods::GET, '/jobs/{id}', $get);
        $router->register(HttpMethods::POST, '/jobs/{id}', $post);

        $match = $router->match(HttpMethods::POST, '/jobs/42');

        $this->assertSame(RouteMatchStatus::Found, $match->status);
        $this->assertSame($post, $match->handler);
        $this->assertSame(['id' => '42'], $match->params);
    }

    #[Test]
    public function match_lists_the_allowed_methods_when_only_the_method_is_wrong(): void
    {
        $router = new Router();
        $router->register(HttpMethods::GET, '/jobs', self::handler());
        $router->register(HttpMethods::POST, '/jobs', self::handler());

        $match = $router->match(HttpMethods::DELETE, '/jobs');

        $this->assertSame(RouteMatchStatus::NotAllowed, $match->status);
        $this->assertSame([HttpMethods::GET, HttpMethods::POST], $match->allowed);
    }

    #[Test]
    public function match_returns_not_found_for_an_unknown_path(): void
    {
        $router = new Router();
        $router->register(HttpMethods::GET, '/jobs', self::handler());

        $this->assertEquals(RouteMatch::notFound(), $router->match(HttpMethods::GET, '/staff'));
    }

    #[Test]
    public function register_throws_for_the_same_method_and_path_twice(): void
    {
        $router = new Router();
        $router->register(HttpMethods::GET, '/jobs', self::handler());

        $this->expectException(VworkError::class);
        $router->register(HttpMethods::GET, '/jobs', self::handler());
    }

    #[Test]
    public function register_throws_after_the_first_match(): void
    {
        $router = new Router();
        $router->match(HttpMethods::GET, '/');

        $this->expectException(VworkError::class);
        $router->register(HttpMethods::GET, '/jobs', self::handler());
    }
}
