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
final class ControllerBaseStub extends ControllerBase
{
    /** @param array<string, mixed> $data */
    public function callView(string $template, array $data = [], HttpStatus $status = HttpStatus::Ok): Response
    {
        return $this->view($template, $data, $status);
    }

    /** @param array<string, mixed> $data */
    public function callPayload(array $data, HttpStatus $status = HttpStatus::Ok): Response
    {
        return $this->payload($data, $status);
    }

    public function callSse(Closure $source): Response
    {
        return $this->sse($source);
    }

    public function callFile(string $path, string $name): Response
    {
        return $this->file($path, $name);
    }
}
