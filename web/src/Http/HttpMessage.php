<?php

declare(strict_types=1);

namespace Vwork\Web\Http;

/**
 * The raw material of an HTTP exchange — before either side has decided
 * whether it's asking for something or answering.
 *
 * Headers, cookies, and body all live here because a Request and a
 * Response are really the same shape wearing two different hats. Bodies
 * are kept as a single raw string, never pre-parsed — some callers need
 * the bytes exactly as they arrived (PayHere's webhook, for one, hashes
 * the untouched body to verify it wasn't tampered with).
 *
 * Deliberately mutable. No withX() cloning: message objects here are
 * built once, adjusted in place, and moved on — no PSR-7 baggage.
 *
 * @author Senira <senirahan@gmail.com>
 */
abstract class HttpMessage
{
    /** @var array<value-of<HttpHeaders>, list<string>> */
    public protected(set) array $headers = [];

    /**
     * Cookies parsed out of the headers — Cookie on a Request,
     * Set-Cookie on a Response.
     *
     * @var array<value-of<HttpCookies>, string>
     */
    abstract public array $cookies { get; }

    /**
     * @param array<value-of<HttpHeaders>, list<string>> $headers
     */
    protected function __construct(array $headers = [])
    {
        $this->headers = $headers;
    }


    public function hasHeader(HttpHeaders $key): bool
    {
        return ($this->headers[$key->value] ?? []) !== [];
    }

    public function addHeader(HttpHeaders $key, string $value): static
    {
        $this->headers[$key->value][] = $value;
        return $this;
    }

    public function rmHeader(HttpHeaders $key): static
    {
        unset($this->headers[$key->value]);
        return $this;
    }


    /**
     * Parses one "name=value" pair. Empty map if
     * the name isn't a cookie we know.
     *
     * @return array<value-of<HttpCookies>, string>
     */
    protected static function parseCookiePair(string $pair): array
    {
        $pair = trim($pair);
        if ($pair === '' || !str_contains($pair, '=')) {
            return [];
        }

        [$name, $value] = explode('=', $pair, 2);
        $cookie = HttpCookies::tryFrom(trim($name));

        return $cookie === null ? [] : [$cookie->value => trim($value)];
    }
}
