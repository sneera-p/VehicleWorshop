<?php

declare(strict_types=1);

namespace Vwork\Web;

use Closure;
use Vwork\Domain\Infrastructure\IInfrastructure;
use Vwork\Domain\Modules\IFacade;
use Vwork\Shared\Collections\ImmutableRegistry;
use Vwork\Shared\Collections\IRegistry;
use Vwork\Web\Controllers\IController;
use Vwork\Web\Http\HttpMethods;
use Vwork\Web\Middleware\IMiddleware;
use Vwork\Web\Pipeline\PipelineFactory;
use Vwork\Web\Router\RouterFactory;
use Vwork\Web\Router\RouterTypes;
use Vwork\Web\Utils\IUtility;

/**
 * Collects everything the app needs, then puts it together once.
 *
 * Boot code hands over the config piece by piece (services, then
 * routes), and finally calls build(). Nothing is created until then.
 *
 * @phpstan-type RouteConfig array{
 *  method: HttpMethods,
 *  path: string,
 *  controller: array{
 *    class: class-string<IController>,
 *    method: string
 *  },
 *  middleware: list<class-string<IMiddleware>>,
 *  context: array<string, mixed>
 * }
 *
 * @phpstan-type ServiceConfigs array<class-string, Closure(IRegistry): object>
 *
 * @author Senira <senirahan@gmail.com>
 */
final class AppBuilder
{
    /** @var ServiceConfigs */
    private array $bindings = [];

    /** @var list<RouteConfig> */
    private array $routes = [];

    private bool $secure = true;
    private RouterTypes $routerType = RouterTypes::Trie;


    /**
     * Add IController, IMiddleware, IUtility, IFacade, IInfrastructure
     *
     * Two config files registering the same class would silently let
     * the second one win. Fail loudly instead.
     *
     * @param ServiceConfigs $config
     *
     * @throws WebError if a class is already registered
     */
    public function addServices(array $config): self
    {
        $taken = array_keys(array_intersect_key($this->bindings, $config));

        if ($taken !== []) {
            throw new WebError('Already registered: ' . implode(', ', $taken));
        }

        $this->bindings = [...$this->bindings, ...$config];
        return $this;
    }

    /**
     * @param list<RouteConfig> $config
     */
    public function addRoutes(array $config): self
    {
        $this->routes = [...$this->routes, ...$config];
        return $this;
    }

    /**
     * Which router to use
     */
    public function withRouter(RouterTypes $type): self
    {
        $this->routerType = $type;
        return $this;
    }

    /**
     * Enable HTTP (restricted by default)
     * Use in development mode
     */
    public function withoutSecure(): self
    {
        $this->secure = false;
        return $this;
    }

    /**
     * Builds every pipeline and registers every route. A route that
     * points at a missing controller or a bad action fails here.
     *
     * @throws WebError if any route is invalid
     */
    public function build(): App
    {
        $registry = new ImmutableRegistry(
            $this->bindings,
            [IController::class, IMiddleware::class, IUtility::class, IFacade::class, IInfrastructure::class]
        );

        $pipelineFactory = new PipelineFactory($registry);
        $routerFactory = new RouterFactory(array_map(
            static fn ($config): array => [
                'method' => $config['method'],
                'path' => $config['path'],
                'handler' => $pipelineFactory->build($config)
            ],
            $this->routes
        ));

        $router = $routerFactory->create($this->routerType);
        return new App($router, $this->secure);
    }
}
