<?php

declare(strict_types=1);

namespace Vwork\Web\Controllers;

use Closure;
use Vwork\Web\Http\HttpStatus;
use Vwork\Web\Http\Headers\HttpHeaders;
use Vwork\Web\Http\Response;
use Vwork\Web\Utils\View;

/**
 * The parent of every controller.
 *
 * A controller reads the request, asks a facade for data, and then picks
 * one of the helpers below to turn that data into a Response.
 *
 * @author Senira <senirahan@gmail.com>
 */
abstract class ControllerBase implements IController
{
    /**
     * Renders a template into an HTML page.
     *
     * @param array<string, mixed> $data becomes the template's local variables
     */
    protected function view(string $template, array $data = [], HttpStatus $status = HttpStatus::Ok): Response
    {
        return Response::html(View::render($template, $data), $status);
    }

    /**
     * Sends $data as JSON.
     *
     * @param array<string, mixed> $data
     */
    protected function payload(array $data, HttpStatus $status = HttpStatus::Ok): Response
    {
        return Response::make(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS),
            [HttpHeaders::ContentType->value => ['application/json; charset=utf-8']],
            $status
        );
    }

    /**
     * Keeps the connection open and sends events as they happen.
     *
     * We hand $source a pen ($emit). Each time something happens, $source
     * writes one event with it, and the event goes straight to the browser.
     * The stream ends when $source returns.
     *
     * @param Closure(Closure(string $event, string $data): void $emit): void $source
     */
    protected function sse(Closure $source): Response
    {
        return Response::stream(
            static function () use ($source): void {
                $source(static function (string $event, string $data): void {
                    echo "event: {$event}\n";

                    // A newline inside data would end the event early.
                    // So each line gets its own "data:" prefix, and the
                    // browser joins them back together.
                    foreach (explode("\n", $data) as $line) {
                        echo "data: {$line}\n";
                    }

                    echo "\n"; // a blank line ends the event
                    flush();
                });
            },
            [
                HttpHeaders::ContentType->value => ['text/event-stream; charset=utf-8'],
                HttpHeaders::CacheControl->value => ['no-cache'],
                HttpHeaders::XAccelBuffering->value => ['no'],
            ],
        );
    }

    /**
     * Sends a file from disk as a download named $name.
     */
    protected function file(string $path, string $name): Response
    {
        return Response::file($path, $name);
    }
}
