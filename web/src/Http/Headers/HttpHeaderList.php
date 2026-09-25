<?php

declare(strict_types=1);

namespace Vwork\Web\Http\Headers;

use ArrayAccess;
use Generator;
use IteratorAggregate;
use Override;
use Vwork\Shared\Types\Cast;
use Vwork\Web\WebError;

/**
 * @implements ArrayAccess<HttpHeaders, list<string>|string>
 * @implements IteratorAggregate<HttpHeaders, list<string>>
 */
final class HttpHeaderList implements ArrayAccess, IteratorAggregate
{
    /** @var array<value-of<HttpHeaders>, list<string>> */
    public private(set) array $list = [];

    /**
     * @param HttpHeaders $offset
     */
    #[Override]
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->list[$offset->value]);
    }

    /**
     * @param HttpHeaders $offset
     * @return list<string>|string|null list for list headers, single value otherwise
     */
    #[Override]
    public function offsetGet(mixed $offset): mixed
    {
        $values = $this->list[$offset->value] ?? null;

        if ($values === null) {
            return null;
        }

        return $offset->isList() ? $values : $values[0];
    }

    /**
     * @param HttpHeaders $offset
     * @param string $value
     * List headers append; everything else replaces.
     */
    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (strpbrk($value, "\r\n\0") !== false) {
            throw new WebError("Invalid value for {$offset->value} header");
        }

        if ($offset->isList()) {
            $this->list[$offset->value][] = $value;
        } else {
            $this->list[$offset->value] = [$value];
        }
    }

    /**
     * @param HttpHeaders $offset
     */
    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        unset($this->list[$offset->value]);
    }

    /**
     * @return Generator<HttpHeaders, list<string>>
     */
    #[Override]
    public function getIterator(): Generator
    {
        foreach ($this->list as $name => $values) {
            yield HttpHeaders::from($name) => $values;
        }
    }

    /**
     * @return iterable<string>
     */
    public function toLines(): iterable
    {
        foreach ($this->list as $name => $values) {
            foreach ($values as $value) {
                yield "{$name}: {$value}";
            }
        }
    }

    /**
     * @param array<string|int, mixed> $server $_SERVER, passed in
     */
    public static function fromServer(array $server): self
    {
        $list = new self();

        foreach (HttpHeaders::serverKeyMap() as $key => $header) {
            if (!isset($server[$key])) {
                continue;
            }

            $raw = Cast::string($server[$key]);
            $values = $header->isList() ? explode(',', $raw) : [$raw];

            foreach ($values as $value) {
                $value = trim($value);
                if ($value !== '') {
                    $list[$header] = $value;
                }
            }
        }

        return $list;
    }

    /**
     * @param array<value-of<HttpHeaders>, list<string>> $headers
     */
    public static function fromArray(array $headers): self
    {
        $list = new self();
        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                $list[HttpHeaders::from($name)] = $value;
            }
        }
        return $list;
    }
}
