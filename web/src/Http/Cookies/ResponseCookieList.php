<?php

declare(strict_types=1);

namespace Vwork\Web\Http\Cookies;

use ArrayAccess;
use Override;
use Vwork\Web\WebError;

/**
 * Cookies the response will set, one Set-Cookie line each.
 * Values are URL-encoded here and decoded by RequestCookies.
 *
 * @implements ArrayAccess<HttpCookies, string>
 */
final class ResponseCookieList implements ArrayAccess
{
    /** @var array<
     *    value-of<HttpCookies>,
     *    array{
     *      value: string,
     *      secure: bool,
     *      path: string,
     *      httpOnly: bool,
     *      sameSite: CookieSameSite,
     *      maxAge: int|null,
     *      domain: string|null
     *    }
     *  >
     */
    public private(set) array $list = [];

    /**
     * @param HttpCookies $offset
     */
    #[Override]
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->list[$offset->value]);
    }

    /**
     * @param HttpCookies $offset
     * @return string | null
     */
    #[Override]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->list[$offset->value]['value'] ?? null;
    }

    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new WebError('Prohibited: Use add(...) or expire(...)');
    }

    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        throw new WebError('Prohibited: Use rm(...)');
    }

    /**
     * @throws WebError on an invalid path/domain, or SameSite=None without Secure
     */
    public function add(
        HttpCookies $key,
        string $value,
        bool $secure = true,
        string $path = '/',
        bool $httpOnly = true,
        CookieSameSite $sameSite = CookieSameSite::Lax,
        ?int $maxAge = null,
        ?string $domain = null,
    ): void {
        if (strpbrk($path, ";,\r\n\0 ") !== false) {
            throw new WebError("Cookie Path: $path is invalid");
        }

        if ($domain !== null && strpbrk($domain, ";,\r\n\0 ") !== false) {
            throw new WebError("Cookie Domain: $domain is invalid");
        }

        if ($sameSite === CookieSameSite::None && !$secure) {
            throw new WebError('SameSite=None requires Secure');
        }

        $this->list[$key->value] = [
            'value' => $value,
            'secure' => $secure,
            'path' => $path,
            'httpOnly' => $httpOnly,
            'sameSite' => $sameSite,
            'maxAge' => $maxAge,
            'domain' => $domain,
        ];
    }

    public function rm(HttpCookies $key): void
    {
        unset($this->list[$key->value]);
    }

    /**
     * Tells the browser to delete a cookie. Path and domain must match
     * what it was set with, or the browser keeps the original.
     */
    public function expire(HttpCookies $key, string $path = '/', ?string $domain = null): void
    {
        $this->add(
            key: $key,
            value: '',
            path: $path,
            maxAge: 0,
            domain: $domain
        );
    }

    /**
     * @return iterable<string>
     */
    public function toLines(): iterable
    {
        foreach ($this->list as $name => [
            'value' => $value,
            'secure' => $secure,
            'path' => $path,
            'httpOnly' => $httpOnly,
            'sameSite' => $sameSite,
            'maxAge' => $maxAge,
            'domain' => $domain,
        ]) {
            $line = "{$name}=" . rawurlencode($value) . "; Path={$path}; SameSite={$sameSite->value}";

            if ($domain !== null) {
                $line .= "; Domain={$domain}";
            }
            if ($maxAge !== null) {
                $line .= "; Max-Age={$maxAge}";
            }
            if ($httpOnly) {
                $line .= '; HttpOnly';
            }
            if ($secure) {
                $line .= '; Secure';
            }

            yield $line;
        }
    }
}
