<?php

declare(strict_types=1);

namespace Vwork\Domain\Infrastructure\Cache;

use Vwork\Domain\Infrastructure\IInfrastructure;

/**
 * Common interface for cache operations
 * @author Senira <senirahan@gmail.com>
 */
interface ICache extends IInfrastructure
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, ?int $ttl = null): void;
    public function has(string $key): bool;
    public function del(string $key): void;
    public function clear(): void;
}
