<?php

declare(strict_types=1);

namespace Vwork\Domain\Infrastructure\Cache;

use Override;
use Redis;
use Vwork\Domain\Infrastructure\InfrastructureException;
use Vwork\Domain\Infrastructure\Internal\Valkey;

/**
 * ICache implementation for Valkey
 * @author Senira <senirahan@gmail.com>
 */
final class ValkeyCache extends Valkey implements ICache
{
    public function __construct(string $host, int $port, string $password)
    {
        parent::__construct($host, $port, $password);
        $this->client->setOption(Redis::OPT_PREFIX, "vwork:cache:");
    }

    public function __destruct()
    {
        parent::__destruct();
    }

    #[Override]
    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        if (!$this->client->set($key, $value, $ttl ? [ 'EX' => $ttl ] : [])) {
            throw new InfrastructureException("Failed to write cache entry", self::class);
        }
    }

    #[Override]
    public function get(string $key): mixed
    {
        $value = $this->client->get($key);
        return ($value !== false)
            ? $value
            : null;
    }

    #[Override]
    public function has(string $key): bool
    {
        return $this->client->exists($key) > 0;
    }

    #[Override]
    public function del(string $key): void
    {
        if (!$this->client->del($key)) {
            throw new InfrastructureException("Failed to write cache entry", self::class);
        }
    }

    #[Override]
    public function clear(): void
    {
        $iterator = null;
        $pattern = $this->client->_prefix('*');

        while (($keys = $this->client->scan($iterator, $pattern)) !== false) {
            if ($keys !== []) {
                $this->client->del(...$keys);
            }
        }
    }
}
