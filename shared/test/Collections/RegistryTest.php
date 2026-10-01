<?php

declare(strict_types=1);

namespace Vwork\Shared\Test\Collections;

use ArrayObject;
use Countable;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use stdClass;
use Vwork\Shared\Collections\Registry;
use Vwork\Shared\Exception\VworkError;
use Vwork\Shared\Test\Stubs\RegistryOwnerStub;

final class RegistryTest extends TestCase
{
    /**
     * @param non-zero-int $attempts
     */
    #[Test]
    #[TestWith([1])]
    #[TestWith([5])]
    #[TestWith([15])]
    public function resolve_builds_once_with_the_owner_and_caches(int $attempts): void
    {
        $calls = 0;
        $seen = null;
        $owner = new RegistryOwnerStub(
            [stdClass::class => [ArrayObject::class => static function (RegistryOwnerStub $o) use (&$calls, &$seen): object {
                $calls++;
                $seen = $o; // what Registry handed the factory
                return new ArrayObject();
            }]],
            [stdClass::class],
        );

        $first = $owner->get(stdClass::class, ArrayObject::class);
        for ($i = 1; $i < $attempts; $i++) {
            $this->assertSame($first, $owner->get(stdClass::class, ArrayObject::class));
        }

        $this->assertSame(1, $calls);
        $this->assertSame($owner, $seen);
    }

    /**
     * @param class-string $category
     * @param class-string $key
     */
    #[Test]
    #[TestWith([Exception::class, ArrayObject::class])] // category not allowed
    #[TestWith([stdClass::class, Exception::class])]    // nothing bound
    #[TestWith([stdClass::class, Countable::class])]    // factory builds the wrong type
    public function resolve_throws_for_a_bad_lookup(string $category, string $key): void
    {
        $registry = new Registry(
            [stdClass::class => [Countable::class => static fn (): object => new stdClass()]],
            [stdClass::class],
            new stdClass(),
        );

        $this->expectException(VworkError::class);
        $registry->resolve($category, $key);
    }

    #[Test]
    public function resolve_throws_on_a_circular_binding(): void
    {
        $owner = new RegistryOwnerStub(
            [stdClass::class => [ArrayObject::class => static fn (RegistryOwnerStub $o): object => $o->get(stdClass::class, ArrayObject::class)]],
            [stdClass::class],
        );

        $this->expectException(VworkError::class);
        $owner->get(stdClass::class, ArrayObject::class);
    }

    #[Test]
    public function the_constructor_rejects_a_category_that_is_not_allowed(): void
    {
        $this->expectException(VworkError::class);
        new Registry([Exception::class => []], [stdClass::class], new stdClass());
    }
}
