<?php

declare(strict_types=1);

namespace Vwork\Domain\Infrastructure\Cache;

use Vwork\Domain\Infrastructure\IInfrastructure;

interface ICache extends IInfrastructure
{
    public function get(string $key): ?string;
    public function set(string $key, mixed $value, int $ttl): void;
}
