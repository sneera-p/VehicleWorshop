<?php

declare(strict_types=1);

namespace Vwork\Web\Registry;

use Vwork\Shared\Exception\VworkError;
use Vwork\Web\Controllers\IController;
use Vwork\Web\Middleware\IMiddleware;
use Vwork\Web\Utils\IUtility;

/**
 * Hands out controllers and middleware by class name.
 * Each one is built on first request and reused after that.
 *
 * @author Senira <senirahan@gmail.com>
 */
interface IHttpRegistry
{
    /**
     * @template T of IController
     * @param class-string<T> $name
     * @return T
     * @throws VworkError if no controller is registered under $name
     *
     * ```php
     *      $controller = $registry->getController(JobController::class)
     * ```
     */
    public function getController(string $name): IController;

    /**
     * @template T of IMiddleware
     * @param class-string<T> $name
     * @return T
     * @throws VworkError if no middleware is registered under $name
     *
     * ```php
     *      $middleware = $registry->getMiddleware(AuthMiddleware::class)
     * ```
     */
    public function getMiddleware(string $name): IMiddleware;

    /**
     * @template T of IUtility
     * @param class-string<T> $name
     * @return T
     * @throws VworkError if no utility is registered under $name
     *
     * ```php
     *      $view = $registry->getUtility(View::class)
     * ```
     */
    public function getUtility(string $name): IUtility;
}
