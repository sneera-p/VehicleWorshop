<?php

declare(strict_types=1);

namespace Vwork\Web\Http;

use Closure;
use Override;
use Vwork\Web\WebError;

/**
 * What goes out.
 *
 * Carries a status, headers, and a body — but doesn't write anything
 * itself. Instead it holds a single closure, `sender`, that knows how to
 * push this particular response to the client, whatever shape that
 * takes: a plain page, a file download, or a long-lived SSE stream. The
 * rest of the app never has to ask which kind it's dealing with.
 *
 * @author Senira <senirahan@gmail.com>
 */
final class Response extends HttpMessage
{
    public private(set) HttpStatus $status;

    /**
     * Computed on every read — addCookie() and friends keep changing
     * $headers underneath.
     *
     * @var array<value-of<HttpCookies>, string>
     */
    #[Override]
    public array $cookies {
        get {
            $acc = [];

            foreach ($this->headers[HttpHeaders::SetCookie->value] ?? [] as $line) {
                [$pair] = explode(';', $line, 2); // drop Path=/Expires=/HttpOnly/etc
                $acc = [...$acc, ...self::parseCookiePair($pair)];
            }

            return $acc;
        }
    }


    /**
     * @param array<value-of<HttpHeaders>, list<string>> $headers
     * @param Closure(): void $sender
     */
    private function __construct(
        HttpStatus $status,
        array $headers,
        private Closure $sender,
    ) {
        parent::__construct($headers);
        $this->status = $status;
    }

    /**
     * 🔴 ⚠️ Writes to PHP's global output state. ⚠️ 🔴
     *
     * Everything upstream builds a Response as plain data; the actual
     * response emitting happens here.
     */
    public function send(): void
    {
        http_response_code($this->status->value);

        foreach ($this->headers as $name => $values) {
            foreach ($values as $value) {
                header("$name: $value", replace: false);
            }
        }

        ($this->sender)();
    }


    /**
     * What every named constructor below delegates to. Wraps $body in a
     * closure that echoes it at send() time.
     *
     * @param array<value-of<HttpHeaders>, list<string>> $headers
     */
    public static function make(string $body, array $headers, HttpStatus $status): self
    {
        return new self(
            $status,
            $headers,
            static function () use ($body): void {
                echo $body;
            }
        );
    }

    /**
     * Shortcut for HTML responses - sets all the correct headers
     *
     * @param string $data - rendered HTML is to be sent from here as a string
     */
    public static function html(string $data, HttpStatus $status = HttpStatus::Ok): self
    {
        return self::make(
            $data,
            [HttpHeaders::ContentType->value => ['text/html; charset=utf-8']],
            $status
        );
    }

    /**
     * Shortcut for simple TEXT responses - sets all the correct headers
     *
     * @param string $data - TEXT is to be sent from here
     */
    public static function text(string $data, HttpStatus $status = HttpStatus::Ok): self
    {
        return self::make(
            $data,
            [HttpHeaders::ContentType->value => ['text/plain; charset=utf-8']],
            $status
        );
    }

    /**
     * For bodies that aren't one known string up front — SSE, chunked output.
     *
     * @param Closure(): void $emit - does all the writing itself; nothing here buffers it.
     * @param array<value-of<HttpHeaders>, list<string>> $headers
     */
    public static function stream(Closure $emit, array $headers): self
    {
        return new self(HttpStatus::Ok, $headers, $emit);
    }

    /**
     * Sends a file from disk as a download.
     *
     * @throws WebError if the file isn't there or its size can't be read
     */
    public static function file(string $path, string $name): self
    {
        if (!is_file($path)) {
            throw new WebError("Cannot send file, not found: {$path}");
        }

        $size = filesize($path);
        if ($size === false) {
            throw new WebError("Cannot determine size of file: {$path}");
        }

        $headers = [
            HttpHeaders::ContentType->value => ['application/octet-stream'],
            HttpHeaders::ContentDisposition->value => ["attachment; filename=\"{$name}\""],
            HttpHeaders::ContentLength->value => [(string) $size],
        ];

        return new self(
            HttpStatus::Ok,
            $headers,
            static function () use ($path): void {
                readfile($path);
            }
        );
    }

    /**
     * Shortcut for Request redirection
     *
     * @param string $path - redirect url
     */
    public static function redirect(string $path, HttpStatus $status = HttpStatus::Found): self
    {
        return self::make(
            '',
            [HttpHeaders::Location->value => [$path]],
            $status
        );
    }

    /**
     * @param list<HttpMethods> $allowed
     */
    public static function methodNotAllowed(array $allowed): self
    {
        $allow = implode(', ', array_map(static fn (HttpMethods $m) => $m->value, $allowed));
        return self::make(
            '',
            [HttpHeaders::Allow->value => [$allow]],
            HttpStatus::MethodNotAllowed
        );
    }

    public static function noContent(): self
    {
        return self::make('', [], HttpStatus::NoContent);
    }

    /**
     * Shortcut for sending many types of errors
     *
     * @param HttpStatus $status - the error Code
     */
    public static function error(HttpStatus $status, string $msg = ''): self
    {
        return self::text($msg, $status);
    }


    public function changeStatus(HttpStatus $status): static
    {
        $this->status = $status;
        return $this;
    }


    /**
     * Renders "name=value; Attr; Attr" as it appears on the wire.
     */
    private function renderCookie(
        HttpCookies $name,
        string $value,
        bool $secure,
        string $path = '/',
        bool $httpOnly = true,
        CookieSameSite $sameSite = CookieSameSite::Lax,
        ?int $maxAge = null,
        ?string $domain = null,
        ?string $expires = null,
    ): string {
        $line = "{$name->value}={$value}; Path={$path}; SameSite={$sameSite->value}";

        if ($domain !== null) {
            $line .= "; Domain={$domain}";
        }
        if ($maxAge !== null) {
            $line .= "; Max-Age={$maxAge}";
        }
        if ($expires !== null) {
            $line .= "; Expires={$expires}";
        }
        if ($httpOnly) {
            $line .= '; HttpOnly';
        }
        if ($secure) {
            $line .= '; Secure';
        }

        return $line;
    }

    /**
     * Queues a Set-Cookie line. Appends rather than replaces —
     * Set-Cookie repeats on the wire, one line per cookie.
     */
    public function addCookie(
        HttpCookies $name,
        string $value,
        bool $secure,
        string $path = '/',
        bool $httpOnly = true,
        CookieSameSite $sameSite = CookieSameSite::Lax,
        ?int $maxAge = null,
        ?string $domain = null,
    ): static {
        $this->headers[HttpHeaders::SetCookie->value][] = $this->renderCookie(
            name: $name,
            value: $value,
            path: $path,
            httpOnly: $httpOnly,
            secure: $secure,
            sameSite: $sameSite,
            maxAge: $maxAge,
            domain: $domain,
        );

        return $this;
    }

    /**
     * Un-queues a line added earlier in THIS response.
     * The browser's own copy is untouched — that's expireCookie().
     */
    public function rmCookie(HttpCookies $name): static
    {
        $lines = array_values(array_filter(
            $this->headers[HttpHeaders::SetCookie->value] ?? [],
            static fn (string $line) => !str_starts_with($line, "{$name->value}="),
        ));

        if ($lines === []) {
            unset($this->headers[HttpHeaders::SetCookie->value]);
        } else {
            $this->headers[HttpHeaders::SetCookie->value] = $lines;
        }

        return $this;
    }

    /**
     * Asks the browser to drop a cookie it holds, by sending an already-
     * expired one. $path and $domain must match what it was set with, or
     * the browser sees a different cookie and ignores this.
     *
     * No Secure: the value is empty, it isn't part of the cookie's
     * identity, and a Secure line dies over plain HTTP.
     */
    public function expireCookie(HttpCookies $name, string $path = '/', ?string $domain = null): static
    {
        $this->headers[HttpHeaders::SetCookie->value][] = $this->renderCookie(
            name: $name,
            value: '',
            secure: false,
            path: $path,
            maxAge: 0,
            domain: $domain,
            expires: 'Thu, 01 Jan 1970 00:00:00 GMT',
        );

        return $this;
    }
}
