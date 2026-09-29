<?php

declare(strict_types=1);

namespace Vwork\Domain\Infrastructure\Database;

use Closure;
use Override;
use PDO;
use PDOException;
use Vwork\Domain\Infrastructure\InfrastructureError;
use Vwork\Domain\Infrastructure\InfrastructureException;

/**
 * IDatabase for postgreSQL database
 * @author Senira <senirahan@gmail.com>
 */
final class PostgresDb implements IDatabase
{
    private const int MAX_RETRIES = 5;
    private const int BACKOFF_MIN = 100;

    private PDO $client;
    private string $dsn;

    public function __construct(
        string $host,
        int $port,
        string $database,
        private string $user,
        private string $password
    ) {
        $this->dsn = "pgsql:host={$host};port={$port};dbname={$database}";
        $this->connect();
    }

    #[Override]
    public function connect(): void
    {
        $error = null;

        for ($attempts = 0; $attempts < self::MAX_RETRIES; $attempts++) {
            try {
                $this->client = new PDO($this->dsn, $this->user, $this->password);
                $this->client->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->client->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
                $this->client->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_NATURAL);
                return;
            } catch (PDOException $e) {
                $error = $e;
                usleep((int) (self::BACKOFF_MIN * 1000 * (2 ** $attempts)));
            }
        }

        throw new InfrastructureError("Unable to connect to Postgres at {$this->dsn}", self::class, $error);
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    #[Override]
    public function query(string $query, array $params): array
    {
        try {
            $statement = $this->client->prepare($query);

            if (!$statement->execute($params)) {
                throw new InfrastructureError("Query failed: {$query}", self::class);
            }

            /** @var list<array<string, mixed>> $rows */
            $rows = array_values($statement->fetchAll(PDO::FETCH_ASSOC));

            return $rows;
        } catch (PDOException $e) {
            throw new InfrastructureException("Query failed: {$e->getMessage()}", self::class, $e);
        }
    }

    /**
     * @param Closure(IDatabase): void $transaction
     */
    #[Override]
    public function execute(Closure $transaction): void
    {
        try {
            $this->beginTransaction();
            $transaction($this);
            $this->commitTransaction();
        } catch (PDOException $e) {
            try {
                $this->rollbackTransaction();
            } catch (PDOException) {
                // Rollback can fail too (e.g. if the transaction never started).
                // Ignore that error so the original failure reaches the caller.
            }
            throw new InfrastructureException("Failed to write transaction", self::class, $e);
        }
    }


    /**
     * @throws PDOException If there is already a transaction started or the driver does not support transactions
     */
    private function beginTransaction(): void
    {
        $this->client->beginTransaction();
    }

    /**
     * @throws PDOException if there is no active transaction.
     */
    private function commitTransaction(): void
    {
        $this->client->commit();
    }

    /**
     * @throws PDOException if there is no active transaction.
     */
    private function rollbackTransaction(): void
    {
        $this->client->rollBack();
    }
}
