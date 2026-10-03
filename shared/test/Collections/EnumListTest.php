<?php

declare(strict_types=1);

namespace Vwork\Shared\Test\Collections;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Vwork\Shared\Collections\EnumList;
use Vwork\Shared\Exception\VworkError;

enum Fruit: string
{
    case Apple = 'apple';
    case Pear = 'pear';
}

enum Level: int
{
    case Low = 5;
    case High = 7;
}

/** Same backing value as Fruit::Apple, to prove the enum type is checked, not just the value. */
enum Colour: string
{
    case Apple = 'apple';
}

final class EnumListTest extends TestCase
{
    #[Test]
    public function reads_back_what_was_stored_and_nothing_else(): void
    {
        $list = EnumList::of(Fruit::class)->with(Fruit::Apple, 'red');

        $this->assertSame('red', $list[Fruit::Apple]);
        $this->assertTrue(isset($list[Fruit::Apple]));
        $this->assertNull($list[Fruit::Pear]);
        $this->assertFalse(isset($list[Fruit::Pear]));
    }

    #[Test]
    public function a_stored_null_still_counts_as_present(): void
    {
        $this->assertTrue(isset(EnumList::of(Fruit::class)->with(Fruit::Apple, null)[Fruit::Apple]));
    }

    #[Test]
    public function with_and_without_return_a_new_list_and_leave_the_original_alone(): void
    {
        $original = EnumList::of(Fruit::class)->with(Fruit::Apple, 'red');

        $replaced = $original->with(Fruit::Apple, 'green');
        $removed = $original->without(Fruit::Apple);

        $this->assertSame('red', $original[Fruit::Apple]);
        $this->assertSame('green', $replaced[Fruit::Apple]);
        $this->assertFalse(isset($removed[Fruit::Apple]));
    }

    #[Test]
    public function with_rejects_a_case_of_another_enum_even_with_the_same_value(): void
    {
        $this->expectException(VworkError::class);
        // @phpstan-ignore argument.type
        EnumList::of(Fruit::class)->with(Colour::Apple, 'red');
    }

    #[Test]
    public function array_writes_are_refused(): void
    {
        $list = EnumList::of(Fruit::class);

        $this->expectException(VworkError::class);
        $list[Fruit::Apple] = 'red';
    }

    #[Test]
    public function array_unsets_are_refused(): void
    {
        $list = EnumList::of(Fruit::class)->with(Fruit::Apple, 'red');

        $this->expectException(VworkError::class);
        unset($list[Fruit::Apple]);
    }

    #[Test]
    public function iterates_enum_cases_in_insertion_order_for_int_backed_enums_too(): void
    {
        $list = EnumList::of(Level::class)->with(Level::High, 'h')->with(Level::Low, 'l')->with(Level::High, 'H');

        $keys = [];
        $values = [];
        foreach ($list as $key => $value) {
            $keys[] = $key;
            $values[] = $value;
        }

        $this->assertSame([Level::High, Level::Low], $keys);
        $this->assertSame(['H', 'l'], $values);
    }

    #[Test]
    public function map_calls_back_with_each_case_and_value(): void
    {
        /** @var EnumList<Fruit, string> $list */
        $list = EnumList::of(Fruit::class)->with(Fruit::Apple, 'red')->with(Fruit::Pear, 'green');

        $lines = iterator_to_array($list->map(static fn (Fruit $k, string $v): string => "{$k->value}={$v}"), false);

        $this->assertSame(['apple=red', 'pear=green'], $lines);
    }
}
