<?php

declare(strict_types=1);

namespace Vwork\Domain\Infrastructure\PubSub;

use Closure;
use Override;
use Redis;
use RedisException;
use Vwork\Domain\Infrastructure\InfrastructureException;
use Vwork\Domain\Infrastructure\Internal\Valkey;

/**
 * IPubSub implementation for Valkey
 * @author Senira <senirahan@gmail.com>
 */
final class ValkeyPubSub extends Valkey implements IPubSub
{
    public function __construct(string $host, int $port, string $password)
    {
        parent::__construct($host, $port, $password);
    }

    public function __destruct()
    {
        parent::__destruct();
    }

    #[Override]
    public function publish(PubSubTopics $topic, string $message): void
    {
        $channel = $topic->value;
        try {
            $this->client->publish($channel, $message);
        } catch (RedisException $e) {
            throw new InfrastructureException("publish failed on topic: $channel, {$e->getMessage()}", self::class, $e);
        }
    }

    /**
     * @param list<PubSubTopics> $topics
     * @param Closure(PubSubTopics $topic, string $message): void $callback
     *
     * BLOCKS the calling process indefinitely until unsubscribed.
     */
    #[Override]
    public function subscribe(array $topics, Closure $callback): void
    {
        $channels = array_map(fn (PubSubTopics $item) => $item->value, $topics);
        try {
            $this->client->subscribe($channels, function (Redis $redis, string $channel, string $message) use ($callback): void {
                $callback(PubSubTopics::from($channel), $message);
            });
        } catch (RedisException $e) {
            $ctx = implode(', ', $channels);
            throw new InfrastructureException("subscribe failed on topics: $ctx; {$e->getMessage()}", self::class, $e);
        }
    }
}
