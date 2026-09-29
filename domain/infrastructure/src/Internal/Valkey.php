<?php

declare(strict_types=1);

namespace Vwork\Domain\Infrastructure\Internal;

use Override;
use Redis;
use RedisException;
use Vwork\Domain\Infrastructure\IInfrastructure;
use Vwork\Domain\Infrastructure\InfrastructureError;

/**
 * Common base class for `ValkeyCache` and `ValkeyPubSub` to inherit from
 *
 * This keeps the connection settings consistent between them (and also less code)
 *
 * @author Senira <senirahan@gmail.com>
 */
abstract class Valkey implements IInfrastructure
{
    private const int MAX_RETRIES = 5;
    private const int MIN_BACKOFF = 100;
    private const int MAX_BACKOFF = 1000;

    protected Redis $client;

    public function __construct(
        protected string $host,
        protected int $port,
        protected string $password,
    ) {
        $this->client = new Redis();
        $this->client->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
        $this->client->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_NONE);
        $this->client->setOption(Redis::OPT_BACKOFF_ALGORITHM, Redis::BACKOFF_ALGORITHM_DECORRELATED_JITTER);
        $this->client->setOption(Redis::OPT_MAX_RETRIES, self::MAX_RETRIES);
        $this->client->setOption(Redis::OPT_BACKOFF_BASE, self::MIN_BACKOFF); // milliseconds
        $this->client->setOption(Redis::OPT_BACKOFF_CAP, self::MAX_BACKOFF);  // milliseconds
        $this->connect();
    }

    public function __destruct()
    {
        $this->client->close();
    }

    #[Override]
    public function connect(): void
    {
        try {
            if (!$this->client->connect($this->host, $this->port)) {
                throw new InfrastructureError("Unable to connect to Valkey at {$this->host}:{$this->port}", static::class);
            }
            if (!$this->client->auth($this->password)) {
                throw new InfrastructureError('Valkey authentication failed', static::class);
            }
        } catch (RedisException $e) {
            throw new InfrastructureError("Unable to connect to Valkey at {$this->host}:{$this->port}", static::class, $e);
        }
    }
}
