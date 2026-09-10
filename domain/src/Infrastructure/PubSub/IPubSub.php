<?php

declare(strict_types=1);

namespace Vwork\Domain\Infrastructure\PubSub;

use Vwork\Domain\Infrastructure\IInfrastructure;
use Vwork\Domain\Infrastructure\PubSub\PubSubTopics;

interface IPubSub extends IInfrastructure
{
    public function publish(PubSubTopics $topic, string $message): void;

    /**
     * @param callable(string): void $handler
     */
    public function subscribe(PubSubTopics $topic, callable $handler): void;
}
