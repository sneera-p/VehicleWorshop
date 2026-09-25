<?php

declare(strict_types=1);

namespace Vwork\Web;

use Vwork\Web\Http\Request;
use Vwork\Web\Http\Response;

/**
 * The whole web app, as one callable.
 *
 * FrankenPHP calls it once per request: frankenphp_handle_request($app).
 * Everything heavy (routes, pipelines, services) was built before the
 * first call, so each call only reads the request and answers it.
 *
 * @author Senira <senirahan@gmail.com>
 */
interface IApp
{
    public function handleRequest(Request $request): Response;
    public function __invoke(): void;
}
