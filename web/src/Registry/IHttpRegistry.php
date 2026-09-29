<?php

declare(strict_types=1);

namespace Vwork\Web\Registry;

use Vwork\Shared\Exception\VworkError;
use Vwork\Web\Controllers\IController;
use Vwork\Web\Middleware\IMiddleware;

/**
 * Hands out controllers and middleware by class name.
 * Each one is built on first request and reused after that.
 *
 * @author Senira <senirahan@gmail.com>
 */
interface IHttpRegistry
{
    /**
     * ```php
     * $registry->getController(JobController::class)
     * ```
     *
     * @param class-string<IController> $name
     * @throws VworkError if no controller is registered under $name
     */
    public function getController(string $name): IController;

    /**
     * ```php
     * $registry->getMiddleware(AuthMiddleware::class)
     * ```
     *
     * @param class-string<IMiddleware> $name
     * @throws VworkError if no middleware is registered under $name
     */
    public function getMiddleware(string $name): IMiddleware;
}
