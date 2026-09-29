<?php

declare(strict_types=1);

namespace Vwork\Web\Pipeline;

use Vwork\Web\Http\Request;
use Vwork\Web\Http\Response;

/**
 * One stop on the way from the router to the controller.
 *
 * A request walks through each middleware and ends at the controller.
 * Every stop gets the request and must give back a Response.
 *
 * @author Senira <senirahan@gmail.com>
 */
interface IPipelineHandler
{
    /**
     * @param array<string, mixed> $attr values from the route and earlier stops
     */
    public function handle(Request $request, array $attr): Response;
}
