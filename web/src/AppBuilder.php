<?php

declare(strict_types=1);

namespace Vwork\Web;

use Override;
use Vwork\Domain\Infrastructure\IInfrastructure;
use Vwork\Domain\Modules\IFacade;
use Vwork\Web\Controllers\IController;
use Vwork\Web\Middleware\IMiddleware;
use Vwork\Web\Pipeline\PipelineFactory;
use Vwork\Web\Registry\AppServiceRegistry;
use Vwork\Web\Router\Router;

/**
 * Collects services and routes, then builds the App in one go.
 *
 * Services are kept on four shelves (infrastructure, facades,
 * controllers, middleware), which is the shape AppServiceRegistry takes.
 *
 * @phpstan-import-type RouteConfig from IAppBuilder
 * @phpstan-import-type ServiceConfig from IAppBuilder
 *
 * @author Senira <senirahan@gmail.com>
 */
final class AppBuilder implements IAppBuilder
{
    /** @var array<class-string, ServiceConfig> category => bindings */
    private array $bindings = [
        IInfrastructure::class => [],
        IFacade::class => [],
        IController::class => [],
        IMiddleware::class => [],
    ];

    /** @var list<RouteConfig> */
    private array $routes = [];

    #[Override]
    public function addInfrastructure(array $config): self
    {
        $this->add(IInfrastructure::class, $config);
        return $this;
    }

    #[Override]
    public function addFacades(array $config): self
    {
        $this->add(IFacade::class, $config);
        return $this;
    }

    #[Override]
    public function addControllers(array $config): self
    {
        $this->add(IController::class, $config);
        return $this;
    }

    #[Override]
    public function addMiddleware(array $config): self
    {
        $this->add(IMiddleware::class, $config);
        return $this;
    }

    #[Override]
    public function addRoutes(array $config): self
    {
        $this->routes = [...$this->routes, ...$config];
        return $this;
    }

    #[Override]
    public function build(): IApp
    {
        $registry = new AppServiceRegistry($this->bindings);
        $factory = new PipelineFactory($registry);
        $router = new Router();

        foreach ($this->routes as $route) {
            $router->register($route['method'], $route['path'], $factory->build($route));
        }

        return new App($router);
    }

    /**
     * Two config files registering the same class would silently let
     * the second one win. Fail loudly instead.
     *
     * @param class-string $category
     * @param ServiceConfig $config
     */
    private function add(string $category, array $config): void
    {
        $taken = array_keys(array_intersect_key($this->bindings[$category], $config));

        if ($taken !== []) {
            throw new WebError('Already registered: ' . implode(', ', $taken));
        }

        $this->bindings[$category] = [...$this->bindings[$category], ...$config];
    }
}
