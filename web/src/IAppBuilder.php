<?php

declare(strict_types=1);

namespace Vwork\Web;

use Closure;
use Vwork\Domain\IDomainRegistry;
use Vwork\Web\Http\HttpMethods;
use Vwork\Web\Controllers\IController;
use Vwork\Web\Middleware\IMiddleware;

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
 * @phpstan-type ServiceConfig array<class-string, Closure(IDomainRegistry): object>
 *
 * @author Senira <senirahan@gmail.com>
 */
interface IAppBuilder
{
    /**
     * @param ServiceConfig $config
     * @throws WebError if a class is already registered
     */
    public function addInfrastructure(array $config): static;

    /**
     * @param ServiceConfig $config
     * @throws WebError if a class is already registered
     */
    public function addFacades(array $config): static;

    /**
     * @param ServiceConfig $config
     * @throws WebError if a class is already registered
     */
    public function addControllers(array $config): static;

    /**
     * @param ServiceConfig $config
     * @throws WebError if a class is already registered
     */
    public function addMiddleware(array $config): static;

    /**
     * @param list<RouteConfig> $config
     */
    public function addRoutes(array $config): static;

    /**
     * Builds every pipeline and registers every route. A route that
     * points at a missing controller or a bad action fails here.
     *
     * @throws WebError if any route is invalid
     */
    public function build(): IApp;
}
