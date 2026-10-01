<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Stubs;

use Vwork\Web\Controllers\ControllerAction;
use Vwork\Web\Controllers\IController;
use Vwork\Web\Http\Request;
use Vwork\Web\Http\Response;

/**
 * A controller for tests.
 *
 * index() is a valid action. The next four each break one action rule.
 * The call* methods make ControllerBase's protected helpers public.
 */
final readonly class ControllerStub implements IController
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
}
