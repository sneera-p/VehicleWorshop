<?php

declare(strict_types=1);

namespace Vwork\Shared\Test\Collections;

use Closure;
use ArrayObject;
use stdClass;
use Exception;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Vwork\Shared\Exception\VworkError;
use Vwork\Shared\Test\Stubs\StubRegistry;

final class RegistryTest extends TestCase
{
    #[Test]
    public function resolve_returns_factory_built_instance(): void
    {
        $expected = new ArrayObject();

        $registry = new StubRegistry(
            bindings: [
                stdClass::class => [
                    ArrayObject::class => fn (StubRegistry $registrar) => $expected,
                ],
            ],
            allowedCategories: [stdClass::class],
        );

        $this->assertSame($expected, $registry->resolvePublic(stdClass::class, ArrayObject::class));
    }

    #[Test]
    #[TestWith([4])]
    #[TestWith([5])]
    #[TestWith([10])]
    public function factory_runs_only_once(int $attempts): void
    {
        $calls = 0;

        $registry = new StubRegistry(
            bindings: [
                stdClass::class => [
                    ArrayObject::class => function (StubRegistry $registrar) use (&$calls): object {
                        $calls++;
                        return new ArrayObject();
                    },
                ],
            ],
            allowedCategories: [stdClass::class],
        );

        for ($i = 0; $i < $attempts; $i++) {
            $registry->resolvePublic(stdClass::class, ArrayObject::class);
        }

        $this->assertSame(1, $calls);
    }

    #[Test]
    public function resolve_passes_registrar_to_factory(): void
    {
        $result = null;

        $registry = new StubRegistry(
            bindings: [
                stdClass::class => [
                    ArrayObject::class => function (StubRegistry $registrar) use (&$result): object {
                        $result = $registrar;
                        return new ArrayObject();
                    },
                ],
            ],
            allowedCategories: [stdClass::class],
        );

        $registry->resolvePublic(stdClass::class, ArrayObject::class);
        $this->assertSame($registry, $result);
    }

    /**
     * @param class-string $category
     * @param class-string $key
     * @param array<class-string, array<class-string, Closure(StubRegistry): object>> $bindings
     * @param list<class-string> $allowedCategories
     */
    #[Test]
    #[TestWith([Exception::class, ArrayObject::class, [], [stdClass::class]])]
    #[TestWith([stdClass::class, Exception::class, [stdClass::class => []], [stdClass::class]])]
    public function resolve_throws_for_invalid_lookup(
        string $category,
        string $key,
        array $bindings,
        array $allowedCategories,
    ): void {
        $registry = new StubRegistry($bindings, $allowedCategories);

        $this->expectException(VworkError::class);
        $registry->resolvePublic($category, $key);
    }

    #[Test]
    public function constructor_throws_for_disallowed_category(): void
    {
        $this->expectException(VworkError::class);

        new StubRegistry(
            bindings: [
                Exception::class => [
                    ArrayObject::class => fn (StubRegistry $registrar): object => new ArrayObject(),
                ],
            ],
            allowedCategories: [stdClass::class], // Exception::class deliberately not included
        );
    }

    #[Test]
    public function different_categories_with_same_key_do_not_collide(): void
    {
        $expected = new ArrayObject();

        $registry = new StubRegistry(
            bindings: [
                stdClass::class => [
                    ArrayObject::class => fn (StubRegistry $r): object => $expected,
                ],
                Exception::class => [
                    ArrayObject::class => fn (StubRegistry $r): object => $expected,
                ],
            ],
            allowedCategories: [stdClass::class, Exception::class],
        );

        $this->assertSame($expected, $registry->resolvePublic(stdClass::class, ArrayObject::class));
        $this->assertSame($expected, $registry->resolvePublic(Exception::class, ArrayObject::class));
    }
}
