<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Mocks;

/**
 * Overrides the "php" stream wrapper so a test can control what
 * file_get_contents('php://input') returns.
 *
 * install()/restore() bracket exactly one test. stream_wrapper_register()
 * works per-scheme, not per-path, so this hijacks the whole "php://"
 * scheme (not just "php://input") for that window — keep it as narrow
 * as a single test method.
 */
final class MockInputStream
{
    private static string $body = '';
    private static bool $installed = false;
    private int $position = 0;

    public static function install(string $body): void
    {
        self::$body = $body;
        if (!self::$installed) {
            stream_wrapper_unregister('php');
            stream_wrapper_register('php', self::class);
            self::$installed = true;
        }
    }

    /** Safe to call even if install() was never called this test. */
    public static function restore(): void
    {
        if (self::$installed) {
            stream_wrapper_restore('php');
            self::$installed = false;
        }
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $this->position = 0;
        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = substr(self::$body, $this->position, $count);
        $this->position += strlen($chunk);
        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen(self::$body);
    }

    /** @return array<int|string, int> */
    public function stream_stat(): array
    {
        return [];
    }

    public function stream_close(): void
    {
    }
}
