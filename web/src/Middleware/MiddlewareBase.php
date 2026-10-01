<?php

declare(strict_types=1);

namespace Vwork\Web\Middleware;

use Vwork\Web\Http\HttpStatus;
use Vwork\Web\Http\Request;
use Vwork\Web\Http\Response;
use Vwork\Web\Middleware\IMiddleware;
use Vwork\Web\Utils\View;

/**
 * The parent of every middleware.
 *
 * A chain of middleware sits before the Request reaches the controller.
 * They are blessed with thy skill to DENY the request and send it back to whence it came
 *
 * @author Senira <senirahan@gmail.com>
 */
abstract readonly class MiddlewareBase implements IMiddleware
{
    public function __construct(
        private View $view
    ) {
    }

    /**
     * Ends the chain here: the controller never runs.
     */
    protected function deny(Request $request, string $message, HttpStatus $status): Response
    {
        $html = $this->view->render('layout/error', [
            'title' => "{$status->value} {$status->description()}",
            'path' => "{$request->method->value} {$request->path}",
            'message' => $message,
        ]);

        return Response::html($html, $status);
    }
}
