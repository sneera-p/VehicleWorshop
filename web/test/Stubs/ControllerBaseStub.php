<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Stubs;

use Closure;
use Vwork\Shared\Collections\IRegistry;
use Vwork\Web\Controllers\ControllerBase;
use Vwork\Web\Http\HttpStatus;
use Vwork\Web\Http\Request;
use Vwork\Web\Http\Response;

/**
 * ControllerBase's helpers are protected. This stub makes them public
 * so tests can call them directly.
 */
final readonly class ControllerBaseStub extends ControllerBase
{
    public function __construct(IRegistry $registry)
    {
        parent::__construct($registry);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function callView(Request $request, string $template, array $data = [], ?string $layout = null, HttpStatus $status = HttpStatus::Ok): Response
    {
        return $this->view($request, $template, $data, $layout, $status);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $accept
     */
    public function callPayload(array $data, array $accept = [], HttpStatus $status = HttpStatus::Ok): Response
    {
        return $this->payload($data, $accept, $status);
    }

    public function callSse(Closure $source): Response
    {
        return $this->sse($source);
    }
}
