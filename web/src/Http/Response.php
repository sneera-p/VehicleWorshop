<?php

declare(strict_types=1);

namespace Vwork\Web\Http;

use Closure;
use Vwork\Shared\Collections\EnumList;
use Vwork\Web\WebError;
use Vwork\Web\Http\Cookies\HttpCookies;
use Vwork\Web\Http\Cookies\CookieSitePolicies;
use Vwork\Web\Http\Headers\ResponseHeaders;

/**
 * What goes out.
 *
 * Carries a status, headers, and a body — but doesn't write anything
 * itself. Instead it holds a single closure, `sender`, that knows how to
 * push this particular response to the client, whatever shape that
 * takes: a plain page, a file download, or a long-lived SSE stream. The
 * rest of the app never has to ask which kind it's dealing with.
 *
 * @phpstan-type ResponseCookie array{
 *  value: string,
 *  path: string,
 *  sameSite: CookieSitePolicies,
 *  maxAge: ?int
 * }
 *
 * @author Senira <senirahan@gmail.com>
 */
final class Response
{
    /** @var EnumList<ResponseHeaders, list<string>> */
    public private(set) EnumList $headers;

    /** @var EnumList<HttpCookies, ResponseCookie> */
    public private(set) EnumList $cookies;

    /**
     * Every header goes through addHeader(), so the same checks apply
     * whether it was set here or added later.
     *
     * @param array<value-of<ResponseHeaders>, list<string>> $headers
     * @param Closure(): void $sender
     * @throws WebError if a header value is invalid
     */
    private function __construct(
        public private(set) HttpStatus $status,
        array $headers,
        private Closure $sender
    ) {
        /** @var EnumList<ResponseHeaders, list<string>> $emptyHeaders */
        $emptyHeaders = EnumList::of(ResponseHeaders::class);
        $this->headers = $emptyHeaders;

        /** @var EnumList<HttpCookies, ResponseCookie> $emptyCookies */
        $emptyCookies = EnumList::of(HttpCookies::class);
        $this->cookies = $emptyCookies;

        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                $this->addHeader(ResponseHeaders::from($name), $value);
            }
        }
    }

    /**
     * What every named constructor below delegates to. Wraps $body in a
     * closure that echoes it at send() time.
     *
     * @param array<value-of<ResponseHeaders>, list<string>> $headers
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
            [ResponseHeaders::ContentType->value => ['text/html; charset=utf-8']],
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
            [ResponseHeaders::ContentType->value => ['text/plain; charset=utf-8']],
            $status
        );
    }

    /**
     * Shortcut to send $data as JSON. - sets all the correct headers
     *
     * @param array<string, mixed> $data - data
     */
    public static function json(array $data, HttpStatus $status = HttpStatus::Ok): self
    {
        return self::make(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS),
            [ResponseHeaders::ContentType->value => ['application/json; charset=utf-8']],
            $status
        );
    }

    /**
     * Shortcut to send $data as CBOR. - sets all the correct headers
     *
     * @param array<string, mixed> $data - data
     */
    public static function cbor(array $data, HttpStatus $status = HttpStatus::Ok): self
    {
        return self::make(
            cbor_encode($data, CBOR_TEXT | CBOR_KEY_TEXT),
            [ResponseHeaders::ContentType->value => ['application/cbor']],
            $status
        );
    }

    /**
     * For bodies that aren't one known string up front — SSE, chunked output.
     *
     * @param Closure(): void $emit - does all the writing itself; nothing here buffers it.
     * @param array<value-of<ResponseHeaders>, list<string>> $headers
     */
    public static function stream(Closure $emit, array $headers): self
    {
        return new self(
            HttpStatus::Ok,
            $headers,
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

        return new self(
            HttpStatus::Ok,
            [
                ResponseHeaders::ContentType->value => ['application/octet-stream'],
                ResponseHeaders::ContentDisposition->value => [
                    "attachment; filename=\"{$ascii}\"; filename*=UTF-8''" . rawurlencode($name),
                ],
                ResponseHeaders::ContentLength->value => [(string) $size],
            ],
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
            [ResponseHeaders::Location->value => [$path]],
            $status
        );
    }

    /**
     * @param list<HttpMethods> $allowed
     */
    public static function methodNotAllowed(array $allowed): self
    {
        return self::make(
            '',
            [ResponseHeaders::Allow->value => array_map(static fn (HttpMethods $v): string => $v->value, $allowed)],
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

    /**
     * List headers (Allow, Cache-Control, Vary) get $value appended;
     * every other header is replaced.
     *
     * @throws WebError if $value contains CR, LF or NUL (header injection)
     */
    public function addHeader(ResponseHeaders $header, string $value): self
    {
        if (strpbrk($value, "\r\n\0") !== false) {
            throw new WebError("Invalid value for {$header->value} header");
        }

        $values = $header->isList() ? [...($this->headers[$header] ?? []), $value] : [$value];
        $this->headers = $this->headers->with($header, $values);
        return $this;
    }

    public function rmHeader(ResponseHeaders $header): self
    {
        $this->headers = $this->headers->without($header);
        return $this;
    }

    /**
     * Queues a Set-Cookie line
     *
     * @throws WebError if $path is not an absolute path, or $maxAge is negative
     */
    public function addCookie(HttpCookies $key, string $value, string $path = '/', CookieSitePolicies $sameSite = CookieSitePolicies::Lax, ?int $maxAge = null): self
    {
        if (strpbrk($path, ";,\r\n\0 ") !== false || !str_starts_with($path, '/')) {
            throw new WebError("Invalid cookie path: {$path}");
        }

        if ($maxAge !== null && $maxAge < 0) {
            throw new WebError('Cookie Max-Age cannot be negative');
        }

        $this->cookies = $this->cookies->with($key, [
            'value' => $value,
            'path' => $path,
            'sameSite' => $sameSite,
            'maxAge' => $maxAge,
        ]);
        return $this;
    }

    /**
     * Un-queues a line added earlier in THIS response.
     * The browser's own copy is untouched — that's expireCookie().
     */
    public function rmCookie(HttpCookies $name): self
    {
        $this->cookies = $this->cookies->without($name);
        return $this;
    }

    /**
     * Asks the browser to drop a cookie it holds, by sending an already-
     * expired one. $path must match what it was set with, or
     * the browser sees a different cookie and ignores this.
     */
    public function expireCookie(HttpCookies $name, string $path = '/'): self
    {
        return $this->addCookie($name, '', $path, maxAge: 0);
    }

    /**
     * 🔴 ⚠️ Writes to PHP's global output state. ⚠️ 🔴
     *
     * Everything upstream builds a Response as plain data; the actual
     * response emitting happens here.
     *
     * List headers go out as one comma-joined line (RFC 9110 §5.3);
     * every other header only ever holds one value.
     */
    public function send(bool $secure): void
    {
        http_response_code($this->status->value);

        foreach ($this->headers as $header => $values) {
            header("{$header->value}: " . implode(', ', $values), replace: false);
        }

        foreach ($this->cookies as $cookie => $c) {
            header(
                $cookie->toLine(
                    $c['value'],
                    $c['path'],
                    $c['sameSite'],
                    $c['maxAge'],
                    $secure
                ),
                replace: false
            );
        }

        ($this->sender)();

        flush();
    }
}
