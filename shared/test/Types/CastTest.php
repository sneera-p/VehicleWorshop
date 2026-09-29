<?php

declare(strict_types=1);

namespace Vwork\Shared\Test\Types;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Shared\Exception\VworkError;
use Vwork\Shared\Types\Cast;

final class CastTest extends TestCase
{
    #[Test]
    #[TestWith(['hello'])]
    #[TestWith([''])]
    #[TestWith(['0'])]
    public function string_returns_strings_unchanged(string $value): void
    {
        $this->assertSame($value, Cast::string($value));
    }

    #[Test]
    #[TestWith([42])]
    #[TestWith([3.14])]
    #[TestWith([true])]
    #[TestWith([null])]
    #[TestWith([[]])]
    public function string_throws_for_non_strings(mixed $value): void
    {
        $this->expectException(VworkError::class);
        Cast::string($value);
    }

    #[Test]
    public function string_throws_for_objects(): void
    {
        $this->expectException(VworkError::class);
        Cast::string(new \stdClass());
    }

    #[Test]
    public function string_error_message_names_the_actual_type(): void
    {
        $this->expectException(VworkError::class);
        $this->expectExceptionMessageMatches('/int/');
        Cast::string(42);
    }

    #[Test]
    public function stringMap_returns_an_all_string_array_unchanged(): void
    {
        $input = ['status' => 'open', 'sort' => 'created_at'];

        $this->assertSame($input, Cast::stringMap($input));
    }

    #[Test]
    public function stringMap_accepts_an_empty_array(): void
    {
        $this->assertSame([], Cast::stringMap([]));
    }

    #[Test]
    public function stringMap_converts_integer_keys_to_strings(): void
    {
        // PHP turns numeric-string array keys into ints, so a query like
        // "?0=a&1=b" arrives with int keys — they still cast to strings
        $this->assertSame(['0' => 'a', '1' => 'b'], Cast::stringMap([0 => 'a', 1 => 'b']));
    }

    #[Test]
    public function stringMap_throws_when_any_value_is_not_a_string(): void
    {
        $this->expectException(VworkError::class);
        Cast::stringMap(['status' => 'open', 'count' => 3]);
    }

    #[Test]
    public function stringMap_throws_for_nested_arrays(): void
    {
        // this is the real $_GET case: "?tags[]=a&tags[]=b" produces
        // ['tags' => ['a', 'b']], which stringMap deliberately rejects
        $this->expectException(VworkError::class);
        Cast::stringMap(['tags' => ['a', 'b']]);
    }
}
