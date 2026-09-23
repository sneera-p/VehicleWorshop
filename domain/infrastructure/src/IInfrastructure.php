<?php

declare(strict_types=1);

namespace Vwork\Domain\Infrastructure;

/**
 * Common interface for all infrastructure in this repo
 *
 * This exists for 2 reasons
 * * reconnect logic via `connect()` function
 * * Solid base type for all infrastructure
 *
 * @author Senira <senirahan@gmail.com>
 */
interface IInfrastructure
{
    /**
     * connect / reconnect sequence in case the infrastructure
     * starts misbehaving like a naughty boy
     */
    public function connect(): void;
}
