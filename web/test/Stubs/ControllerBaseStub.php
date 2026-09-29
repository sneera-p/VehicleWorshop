<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Stubs;

use Closure;
use Vwork\Web\Controllers\ControllerBase;
use Vwork\Web\Http\HttpStatus;
use Vwork\Web\Http\Response;

/**
 * ControllerBase's helpers are protected. This stub makes them public
 * so tests can call them directly.
 */
class ControllerBaseStub extends ControllerBase
{
    /**
     * @param array<string, mixed> $data
     */
    public static function callView(string $template, array $data = [], ?string $layout = null, HttpStatus $status = HttpStatus::Ok): Response
    {
        return self::view($template, $data, $layout, $status);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $accept
     */
    public static function callPayload(array $data, array $accept = [], HttpStatus $status = HttpStatus::Ok): Response
    {
        return self::payload($data, $accept, $status);
    }

    public static function callSse(Closure $source): Response
    {
        return self::sse($source);
    }
}
