<?php

declare(strict_types=1);

namespace Vwork\Domain\Infrastructure\PubSub;

use Closure;
use Vwork\Domain\Infrastructure\IInfrastructure;
use Vwork\Domain\Infrastructure\PubSub\PubSubTopics;

/**
 * Common interface for pubsub operations
 * @author Senira <senirahan@gmail.com>
 */
interface IPubSub extends IInfrastructure
{
    public function publish(PubSubTopics $topic, string $message): void;

    /**
     * @param list<PubSubTopics> $topics
     * @param Closure(PubSubTopics $topic, string $message): void $callback
     */
    public function subscribe(array $topics, Closure $callback): void;
}
