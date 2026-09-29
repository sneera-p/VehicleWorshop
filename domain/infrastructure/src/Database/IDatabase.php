<?php

declare(strict_types=1);

namespace Vwork\Domain\Infrastructure\Database;

use Closure;
use Vwork\Domain\Infrastructure\IInfrastructure;

/**
 * Common interface for database operations
 * @author Senira <senirahan@gmail.com>
 */
interface IDatabase extends IInfrastructure
{
    /**
     * @param Closure(IDatabase): void $transaction
     */
    public function execute(Closure $transaction): void;

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function query(string $query, array $params): array;
}
