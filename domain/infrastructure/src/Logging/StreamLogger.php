<?php

declare(strict_types=1);

namespace Vwork\Domain\Infrastructure\Logging;

use DateTimeImmutable;
use Override;
use Vwork\Domain\Infrastructure\Cache\ICache;
use Vwork\Domain\Infrastructure\InfrastructureError;

/**
 * Logs to anything with a hole. I mean a Stream endpoint
 *
 * Uses Cache to store the format and min-level that needs to be logged
 * This means we can just change the format or min-levle by adjusting the relevant fields
 * * format:    '{PREFIX}:log:format'
 * * min-level: '{PREFIX}:log:min_level'
 *
 * @author Senira <senirahan@gmail.com>
 */
final class StreamLogger implements ILogger
{
    /**
     * @var resource $stream
     */
    private $stream;

    public function __construct(
        private ICache $cache,
        private string $path = 'php://stdout',
    ) {
        $this->connect();
    }

    public function __destruct()
    {
        fclose($this->stream);
    }

    #[Override]
    public function connect(): void
    {
        $handle = fopen($this->path, 'a');
        if ($handle === false) {
            throw new InfrastructureError("Unable to open log stream: {$this->path}", self::class);
        }
        $this->stream = $handle;
    }

    public LogLevels $minLevel {
        get {
            /** @var string | null $entry */
            $entry = $this->cache->get('log:min_level');
            return $entry
                ? LogLevels::from($entry)
                : LogLevels::Debug;
        }
    }

    public string $format {
        get {
            /** @var string | null $entry */
            $entry = $this->cache->get('log:format');

            if ($entry === null) {
                return '[@{ts}] [@{lvl}] @{msg} (@{ctx})';
            }

            if (self::isValidFormat($entry)) {
                return $entry;
            }

            throw new InfrastructureError("StreamLogger Format Invalid", self::class);
        }
    }

    private static function isValidFormat(string $format): bool
    {
        $required = ['ts', 'lvl', 'msg', 'ctx'];
        preg_match_all('/\@{(\w+)\}/', $format, $matches);

        // all 4 required tokens must be present — if any are missing, invalid
        if (array_diff($required, $matches[1]) !== []) {
            return false;
        }

        // anything captured that isn't one of the 4 required tokens is invalid
        if (array_diff($matches[1], $required) !== []) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function write(LogLevels $level, string $message, array $context): void
    {
        if ($level->value < $this->minLevel->value) {
            return;
        }

        $line = strtr($this->format, [
            '@{ts}' => (new DateTimeImmutable("now"))->format(DATE_ATOM),
            '@{lvl}' => $level->description(),
            '@{msg}' => $message,
            '@{ctx}' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ]);

        fwrite($this->stream, $line . PHP_EOL);
    }

    /**
     * @param array<string, mixed> $context
     */
    #[Override]
    public function debug(string $message, array $context = []): void
    {
        $this->write(LogLevels::Debug, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    #[Override]
    public function info(string $message, array $context = []): void
    {
        $this->write(LogLevels::Info, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    #[Override]
    public function warning(string $message, array $context = []): void
    {
        $this->write(LogLevels::Warn, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    #[Override]
    public function error(string $message, array $context = []): void
    {
        $this->write(LogLevels::Error, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    #[Override]
    public function critical(string $message, array $context = []): void
    {
        $this->write(LogLevels::Critical, $message, $context);
    }
}
