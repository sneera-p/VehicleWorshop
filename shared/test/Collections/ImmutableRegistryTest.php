<?php

declare(strict_types=1);

namespace Vwork\Shared\Test\Collections;

use ArrayIterator;
use ArrayObject;
use Countable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Vwork\Shared\Collections\IRegistry;
use Vwork\Shared\Collections\ImmutableRegistry;
use Vwork\Shared\Exception\VworkError;

/**
 * Bindings use ArrayObject and ArrayIterator: both are Countable (the one
 * allowed base type), but neither is the other, so a wrong build is easy.
 */
final class ImmutableRegistryTest extends TestCase
{
    /**
     * @param array<class-string, \Closure(IRegistry): mixed> $bindings
     */
    private static function registry(array $bindings): ImmutableRegistry
    {
        /** @phpstan-ignore argument.type (tests feed deliberately bad closures) */
        return new ImmutableRegistry($bindings, [Countable::class]);
    }

    #[Test]
    public function builds_lazily_and_only_once(): void
    {
        $calls = 0;
        $registry = self::registry([
            ArrayObject::class => static function () use (&$calls): ArrayObject {
                $calls++;
                return new ArrayObject();
            },
        ]);

        $this->assertSame(0, $calls);
        $this->assertSame($registry->get(ArrayObject::class), $registry->get(ArrayObject::class));
        $this->assertSame(1, $calls);
    }

    #[Test]
    public function passes_itself_to_the_binding(): void
    {
        $registry = self::registry([
            ArrayIterator::class => static fn (): ArrayIterator => new ArrayIterator(),
            ArrayObject::class => static fn (IRegistry $r): ArrayObject => new ArrayObject([$r->get(ArrayIterator::class)]),
        ]);

        $this->assertSame($registry->get(ArrayIterator::class), $registry->get(ArrayObject::class)[0]);
    }

    #[Test]
    public function throws_when_nothing_is_bound(): void
    {
        $this->expectException(VworkError::class);
        self::registry([])->get(ArrayObject::class);
    }

    #[Test]
    public function rejects_a_binding_outside_the_allowed_types_at_construction(): void
    {
        $this->expectException(VworkError::class);
        self::registry([stdClass::class => static fn (): stdClass => new stdClass()]);
    }

    #[Test]
    public function throws_when_a_binding_builds_the_wrong_type(): void
    {
        $registry = self::registry([ArrayObject::class => static fn (): ArrayIterator => new ArrayIterator()]);

        $this->expectException(VworkError::class);
        $registry->get(ArrayObject::class);
    }

    #[Test]
    public function throws_on_a_circular_binding(): void
    {
        $registry = self::registry([
            ArrayObject::class => static fn (IRegistry $r): object => $r->get(ArrayIterator::class),
            ArrayIterator::class => static fn (IRegistry $r): object => $r->get(ArrayObject::class),
        ]);

        $this->expectException(VworkError::class);
        $registry->get(ArrayObject::class);
    }

    #[Test]
    public function a_failed_build_is_not_mistaken_for_a_cycle_on_retry(): void
    {
        $database = new stdClass();
        $database->down = true;

        $registry = self::registry([
            // @phpstan-ignore ternary.alwaysTrue
            ArrayObject::class => static fn (): ArrayObject => $database->down
                ? throw new VworkError('database down')
                : new ArrayObject(),
        ]);

        try {
            $registry->get(ArrayObject::class);
        } catch (VworkError) {
        }
        $database->down = false;

        $this->assertInstanceOf(ArrayObject::class, $registry->get(ArrayObject::class));
    }
}
