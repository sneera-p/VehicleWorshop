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
     * Renders $template into an HTML response, optionally wrapped in $layout.
     *
     * The template receives all of $data as local variables.
     *
     * The layout receives only two:
     *  - 'content' (the rendered template)
     *  - 'title' (defaults to 'No Title')
     *
     * @param string $template - view to render, relative to VIEW_PATH
     * @param array<string, mixed> $data - the template's local variables.
     *     ('title', if present, is also passed to the layout)
     * @param ?string $layout - wrapping view; must echo $content and $title.
     *     (null sends the template on its own)
     */
    protected static function view(string $template, array $data = [], ?string $layout = null, HttpStatus $status = HttpStatus::Ok): Response
    {
        $html = View::render($template, $data);

        if ($layout !== null) {
            $html = View::render($layout, [
                'content' => $html,
                'title' => $data['title'] ?? 'No Title',
            ]);
        }

        return Response::html($html, $status);
    }

    /**
     * Sends $data as JSON or CBOR, depending on Accept headers.
     *
     * @param array<string, mixed> $data
     * @param list<string> $accept
     */
    protected static function payload(array $data, array $accept, HttpStatus $status = HttpStatus::Ok): Response
    {
        $response = match (self::negotiatePayloadType($accept)) {
            'application/json' => Response::json($data, $status),
            'application/cbor' => Response::cbor($data, $status),
            null => Response::error(HttpStatus::NotAcceptable, 'Supported: application/json, application/cbor'),
        };

        return $response->addHeader(HttpHeaders::Vary, 'Accept');
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
    protected static function sse(Closure $source): Response
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
     * Picks the client's most preferred supported type: highest q wins,
     * a specific type beats a wildcard at the same q. Empty Accept means JSON.
     *
     * @param list<string> $accept
     * @return 'application/json'|'application/cbor'|null
     */
    private static function negotiatePayloadType(array $accept): ?string
    {
        if ($accept === []) {
            return 'application/json';
        }

        $best = null;
        $bestQ = 0.0;
        $bestIsWildcard = true;

        foreach ($accept as $range) {
            $params = array_map('trim', explode(';', $range));

            // parse q value
            $q = 1.0;
            foreach ($params as $param) {
                if (str_starts_with($param, 'q=')) {
                    $q = (float) substr($param, 2);
                }
            }

            // parse Content-Type
            $type = strtolower(array_shift($params));
            [$match, $isWildcard] = match ($type) {
                'application/json', 'application/cbor' => [$type, false],
                '*/*', 'application/*' => ['application/json', true],
                default => [null, true],
            };

            if ($match === null || $q <= 0.0) {
                continue;
            }

            if ($q > $bestQ || ($q === $bestQ && $bestIsWildcard && !$isWildcard)) {
                $best = $match;
                $bestQ = $q;
                $bestIsWildcard = $isWildcard;
            }
        }

        return $best;
    }
}
