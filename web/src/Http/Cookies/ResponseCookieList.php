<?php

declare(strict_types=1);

namespace Vwork\Web\Http\Cookies;

use ArrayAccess;
use Override;
use Vwork\Web\WebError;

/**
 * Cookies the response will set, one Set-Cookie line each..
 *
 * @implements ArrayAccess<HttpCookies, string>
 */
final class ResponseCookieList implements ArrayAccess
{
    /**
     * @var array<value-of<HttpCookies>, array{
     *  value: string,
     *  path: string,
     *  sameSite: CookieSitePolicies,
     *  maxAge: int|null,
     * }>
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

    public function add(HttpCookies $key, string $value, string $path = '/', CookieSitePolicies $sameSite = CookieSitePolicies::Lax, ?int $maxAge = null): void
    {
        if (strpbrk($path, ";,\r\n\0 ") !== false || !str_starts_with($path, '/')) {
            throw new WebError("Invalid cookie path: {$path}");
        }

        if ($maxAge !== null && $maxAge < 0) {
            throw new WebError('Cookie Max-Age cannot be negative');
        }

        $this->list[$key->value] = [
            'value' => $value,
            'path' => $path,
            'sameSite' => $sameSite,
            'maxAge' => $maxAge
        ];
    }

    public function rm(HttpCookies $key): void
    {
        unset($this->list[$key->value]);
    }

    /**
     * Tells the browser to delete a cookie. Path must match
     * what it was set with, or the browser keeps the original.
     */
    public function expire(HttpCookies $key, string $path = '/'): void
    {
        $this->add($key, '', $path, maxAge: 0);
    }

    /**
     * @return iterable<string>
     */
    public function toLines(bool $secure): iterable
    {
        foreach ($this->list as $name => $settings) {
            yield HttpCookies::from($name)->toLine(
                value: $settings['value'],
                path: $settings['path'],
                sameSite: $settings['sameSite'],
                maxAge: $settings['maxAge'],
                secure: $secure
            );
        }
    }
}
