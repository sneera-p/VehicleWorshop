<?php

declare(strict_types=1);

namespace Vwork\Web;

use Closure;
use Vwork\Domain\Infrastructure\IInfrastructure;
use Vwork\Domain\Modules\IFacade;
use Vwork\Web\Controllers\IController;
use Vwork\Web\Http\HttpMethods;
use Vwork\Web\Middleware\IMiddleware;
use Vwork\Web\Pipeline\PipelineFactory;
use Vwork\Web\Registry\AppRegistry;
use Vwork\Web\Registry\IAppRegistry;
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
 * @phpstan-type ServiceConfig array<class-string, Closure(IAppRegistry): object>
 *
 * @author Senira <senirahan@gmail.com>
 */
final class AppBuilder
{
    /** @var array<class-string, ServiceConfig> category => bindings */
    private array $bindings = [
        IInfrastructure::class => [],
        IFacade::class => [],
        IUtility::class => [],
        IController::class => [],
        IMiddleware::class => [],
    ];

    /** @var list<RouteConfig> */
    private array $routes = [];

    private bool $secure = true;
    private RouterTypes $routerType;

    /**
     * @param ServiceConfig $config
     * @throws WebError if a class is already registered
     */
    public function addInfrastructure(array $config): self
    {
        $this->addService(IInfrastructure::class, $config);
        return $this;
    }

    /**
     * @param ServiceConfig $config
     * @throws WebError if a class is already registered
     */
    public function addFacades(array $config): self
    {
        $this->addService(IFacade::class, $config);
        return $this;
    }

    /**
     * @param ServiceConfig $config
     * @throws WebError if a class is already registered
     */
    public function addUtils(array $config): self
    {
        $this->addService(IUtility::class, $config);
        return $this;
    }

    /**
     * @param ServiceConfig $config
     * @throws WebError if a class is already registered
     */
    public function addControllers(array $config): self
    {
        $this->addService(IController::class, $config);
        return $this;
    }

    /**
     * @param ServiceConfig $config
     * @throws WebError if a class is already registered
     */
    public function addMiddleware(array $config): self
    {
        $this->addService(IMiddleware::class, $config);
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
        $registry = new AppRegistry($this->bindings);

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

    /**
     * Two config files registering the same class would silently let
     * the second one win. Fail loudly instead.
     *
     * @param class-string $category
     * @param ServiceConfig $config
     */
    private function addService(string $category, array $config): void
    {
        $taken = array_keys(array_intersect_key($this->bindings[$category], $config));

        if ($taken !== []) {
            throw new WebError('Already registered: ' . implode(', ', $taken));
        }

        $this->bindings[$category] = [...$this->bindings[$category], ...$config];
    }
}
