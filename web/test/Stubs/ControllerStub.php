<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Stubs;

use Closure;
use Vwork\Web\Controllers\ControllerAction;
use Vwork\Web\Controllers\ControllerBase;
use Vwork\Web\Http\HttpStatus;
use Vwork\Web\Http\Request;
use Vwork\Web\Http\Response;

/**
 * A controller for tests.
 *
 * index() is a valid action. The next four each break one action rule.
 * The call* methods make ControllerBase's protected helpers public.
 */
final class ControllerStub extends ControllerBase
{
    /** @param array<string, mixed> $attr */
    #[ControllerAction]
    public function index(Request $request, array $attr): Response
    {
        return Response::text('index');
    }

    // Each method below breaks exactly one ControllerAction rule.

    /** @param array<string, mixed> $attr */
    public function notMarked(Request $request, array $attr): Response
    {
        return Response::text('');
    }

    /** @param array<string, mixed> $attr */
    #[ControllerAction]
    protected function notPublic(Request $request, array $attr): Response
    {
        return Response::text('');
    }

    #[ControllerAction]
    public function wrongParams(Request $request): Response
    {
        return Response::text('');
    }

    /** @param array<string, mixed> $attr */
    #[ControllerAction]
    public function wrongReturn(Request $request, array $attr): string
    {
        return '';
    }

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
