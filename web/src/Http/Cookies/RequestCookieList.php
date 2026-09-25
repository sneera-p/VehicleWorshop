<?php

declare(strict_types=1);

namespace Vwork\Web\Http\Cookies;

use ArrayAccess;
use Override;
use Vwork\Web\WebError;
use Vwork\Web\Http\Headers\HttpHeaders;
use Vwork\Web\Http\Headers\HttpHeaderList;

/**
 * Cookies the browser sent, parsed from the Cookie header.
 * Read-only: just name → value, no attributes.
 *
 * @implements ArrayAccess<HttpCookies, string>
 */
final class RequestCookieList implements ArrayAccess
{
    /** @param array<value-of<HttpCookies>, string> $list */
    private function __construct(
        public readonly array $list,
    ) {
    }

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
        return $this->list[$offset->value] ?? null;
    }

    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new WebError('Cannot mutate request list');
    }

    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        throw new WebError('Cannot mutate request list');
    }


    public static function fromHeader(HttpHeaderList $headers): self
    {
        /** @var string */
        $raw = $headers[HttpHeaders::Cookie] ?? '';
        $list = [];

        foreach (explode(';', $raw) as $pair) {
            [$name, $value] = array_pad(explode('=', trim($pair), 2), 2, '');
            // unknown names are ignored; the enum is the allowlist
            if (HttpCookies::tryFrom($name) !== null) {
                $list[$name] = rawurldecode($value);
            }
        }

        /** @var array<value-of<HttpCookies>, string> $list */
        return new self($list);
    }
}
