<?php

declare(strict_types=1);

namespace Vwork\Shared\Collections;

use ArrayAccess;
use BackedEnum;
use Closure;
use Generator;
use IteratorAggregate;
use Override;
use Vwork\Shared\Exception\VworkError;

/**
 * @template K of BackedEnum
 * @template T
 *
 * @implements ArrayAccess<K, T>
 * @implements IteratorAggregate<K, T>
 */
final readonly class EnumList implements ArrayAccess, IteratorAggregate
{
    /**
     * @param class-string<K> $enum
     * @param array<value-of<K>, T> $list
     */
    private function __construct(
        public string $enum,
        public array $list
    ) {
    }

    /**
     * @template E of BackedEnum
     *
     * @param class-string<E> $enum
     * @return self<E, mixed>
     */
    public static function of(string $enum): self
    {
        return new self($enum, []);
    }

    /**
     * @template E of BackedEnum
     * @template V
     * @param class-string<E> $enum
     * @param array<value-of<E>, V> $list
     * @return self<E, V>
     * @throws VworkError if a key is not a value of $enum
     */
    public static function fromArray(string $enum, array $list): self
    {
        foreach (array_keys($list) as $value) {
            $enum::tryFrom($value) ?? throw new VworkError("{$value} is not a case of {$enum}");
        }
        return new self($enum, $list);
    }

    /**
     * @param K $offset
     * @param T $value
     *
     * @return self<K, T>
     */
    public function with(BackedEnum $offset, mixed $value): self
    {
        if (!$offset instanceof $this->enum) {
            throw new VworkError($offset::class . " is not a {$this->enum}");
        }

        $list = $this->list;
        $list[$offset->value] = $value;
        return new self($this->enum, $list);
    }

    /**
     * @param K $offset
     *
     * @return self<K, T>
     */
    public function without(BackedEnum $offset): self
    {
        if (!$offset instanceof $this->enum) {
            throw new VworkError($offset::class . " is not a {$this->enum}");
        }

        $list = $this->list;
        unset($list[$offset->value]);
        return new self($this->enum, $list);
    }

    /**
     * @param K $offset
     */
    #[Override]
    public function offsetExists(mixed $offset): bool
    {
        // return isset($this->list[$offset->value]);
        return array_key_exists($offset->value, $this->list);
    }

    /**
     * @param K $offset
     * @return T | null
     */
    #[Override]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->list[$offset->value] ?? null;
    }

    /**
     * Invalid function - EnumList is Immutable,
     * implemented to satisfy ArrayAccess
     *
     * use EnumList::with(...)
     *
     * @throws VworkError
     */
    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new VworkError('Immutable class, use self::with(...)');
    }

    /**
     * Invalid function - EnumList is Immutable,
     * implemented to satisfy ArrayAccess
     *
     * use EnumList::without(...)
     *
     * @throws VworkError
     */
    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        throw new VworkError('Immutable class, use self::without(...)');
    }

    /**
     * @return Generator<K, T>
     */
    #[Override]
    public function getIterator(): Generator
    {
        foreach ($this->list as $name => $value) {
            yield ($this->enum)::from($name) => $value;
        }
    }

    /**
     * @template S
     * @param Closure(K, T): S $callback
     * @return Generator<int, S>
     */
    public function map(Closure $callback): Generator
    {
        foreach ($this as $key => $value) {
            yield $callback($key, $value);
        }
    }
}
