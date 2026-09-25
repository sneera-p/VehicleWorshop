<?php

declare(strict_types=1);

namespace Vwork\Web\Controllers;

use Closure;
use Vwork\Web\Http\HttpStatus;
use Vwork\Web\Http\Headers\HttpHeaders;
use Vwork\Web\Http\Response;
use Vwork\Web\Utils\View;

/**
 * Common base class for controllers with helper functions
 *
 * @author Senira <senirahan@gmail.com>
 */
abstract class ControllerBase
{
    /**
     * @param array<string, mixed> $data
     */
    protected function view(string $template, array $data, HttpStatus $status): Response
    {
        return Response::html(
            View::render($template, $data),
            $status
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function payload(array $data, HttpStatus $status): Response
    {
        return Response::make(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS),
            [HttpHeaders::ContentType->value => ['application/json; charset=utf-8']],
            $status
        );
    }

    /**
     * @param Closure(Closure(string $event, string $data): void $emitter): void $source
     */
    protected function sse(Closure $source): Response
    {
        return Response::stream(
            static function () use ($source): void {
                $source(static function (string $event, string $data): void {
                    echo "event: {$event}\n";
                    foreach (explode("\n", $data) as $line) {
                        echo "data: {$line}\n";
                    }
                    echo "\n";
                    flush();
                });
            },
            [HttpHeaders::ContentType->value => ['text/event-stream; charset=utf-8']],
        );
    }

    protected function file(string $path, string $name): Response
    {
        return Response::file($path, $name);
    }
}
