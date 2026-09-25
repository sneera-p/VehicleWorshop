<?php

declare(strict_types=1);

namespace Vwork\Web\Http;

use Closure;
use Override;
use Vwork\Web\WebError;
use Vwork\Web\Http\Headers\HttpHeaders;
use Vwork\Web\Http\Headers\HttpHeaderList;
use Vwork\Web\Http\Cookies\HttpCookies;
use Vwork\Web\Http\Cookies\ResponseCookieList;
use Vwork\Web\Http\Cookies\CookieSameSite;

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
final class Response
{
    public private(set) ResponseCookieList $cookies;

    /**
     * @param Closure(): void $sender
     */
    private function __construct(
        public private(set) HttpStatus $status,
        public private(set) HttpHeaderList $headers,
        private Closure $sender
    ) {
        $this->cookies = new ResponseCookieList();
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
            HttpHeaderList::fromArray($headers),
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
        return new self(
            HttpStatus::Ok,
            HttpHeaderList::fromArray($headers),
            $emit
        );
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

        $ascii = addcslashes(preg_replace('/[^\x20-\x7E]/', '_', $name) ?? 'download', '"\\');

        $headers = [
            HttpHeaders::ContentType->value => ['application/octet-stream'],
            HttpHeaders::ContentDisposition->value => [
                "attachment; filename=\"{$ascii}\"; filename*=UTF-8''" . rawurlencode($name),
            ],
            HttpHeaders::ContentLength->value => [(string) $size],
        ];

        return new self(
            HttpStatus::Ok,
            HttpHeaderList::fromArray($headers),
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


    public function changeStatus(HttpStatus $status): self
    {
        $this->status = $status;
        return $this;
    }


    public function addHeader(HttpHeaders $header, string $value): self
    {
        if (!$header->isResponseHeader()) {
            throw new WebError("Header {$header->value} cannot be attached to Http Response");
        }

        if ($header === HttpHeaders::SetCookie) {
            throw new WebError("Prohibited: Use addCookie(...)");
        }

        $this->headers[$header] = $value;
        return $this;
    }

    public function rmHeader(HttpHeaders $header): self
    {
        unset($this->headers[$header]);
        return $this;
    }


    /**
     * Queues a Set-Cookie line. Appends rather than replaces —
     * Set-Cookie repeats on the wire, one line per cookie.
     */
    public function addCookie(
        HttpCookies $name,
        string $value,
        bool $secure = true,
        string $path = '/',
        bool $httpOnly = true,
        CookieSameSite $sameSite = CookieSameSite::Lax,
        ?int $maxAge = null,
        ?string $domain = null,
    ): self {
        $this->cookies->add(
            $name,
            $value,
            $secure,
            $path,
            $httpOnly,
            $sameSite,
            $maxAge,
            $domain
        );
        return $this;
    }

    /**
     * Un-queues a line added earlier in THIS response.
     * The browser's own copy is untouched — that's expireCookie().
     */
    public function rmCookie(HttpCookies $name): self
    {
        $this->cookies->rm($name);
        return $this;
    }

    /**
     * Asks the browser to drop a cookie it holds, by sending an already-
     * expired one. $path and $domain must match what it was set with, or
     * the browser sees a different cookie and ignores this.
     */
    public function expireCookie(HttpCookies $name, string $path = '/', ?string $domain = null): self
    {
        $this->cookies->expire($name, $path, $domain);
        return $this;
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

        foreach ($this->headers->toLines() as $line) {
            header($line, replace: false);
        }

        foreach ($this->cookies->toLines() as $line) {
            header($line, replace: false);
        }

        ($this->sender)();

        flush();
    }
}
