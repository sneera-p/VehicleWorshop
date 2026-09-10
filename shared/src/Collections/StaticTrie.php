<?php

declare(strict_types=1);

namespace Vwork\Shared\Collections;

use SebastianBergmann\CodeCoverage\MethodNotImplementedException;

final class StaticTrie
{
    public function insert(string $key, mixed $value): void
    {
        throw new MethodNotImplementedException();
    }

    public function build(): void
    {
        throw new MethodNotImplementedException();
    }

    public function search(string $key): mixed
    {
        throw new MethodNotImplementedException();
    }
}
