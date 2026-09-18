<?php

declare(strict_types=1);

namespace Vwork\Web\Http;

/**
 * One uploaded file, lifted out of PHP's $_FILES representation.
 *
 * $_FILES has two different native shapes depending on whether the input
 * field was single- or multi-file:
 *
 *   single: ['name' => 'a.jpg', 'tmp_name' => '/tmp/1', ...]
 *   multi:  ['name' => ['a.jpg', 'b.jpg'], 'tmp_name' => ['/tmp/1', '/tmp/2'], ...]
 *
 * Multi-file fields keep the same five keys but turn each into a parallel,
 * index-aligned array (SoA) rather than nesting per file (AoS) — hence the two named
 * constructors below.
 * 
 * @author Senira <senirahan@gmail.com>
 */
final class UploadedFile
{
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly string $type,
        public readonly int $size,
        public readonly int $error,
    ) {
    }

    /**
     * Extract from single-file entry
     *
     * @param array{
     *     name: string,
     *     tmp_name: string,
     *     type: string,
     *     size: int,
     *     error: int
     * } $data
     */
    public static function fromSingleArray(array $data): self
    {
        return new self(
            name: $data['name'],
            path: $data['tmp_name'],
            type: $data['type'],
            size: $data['size'],
            error: $data['error'],
        );
    }

    /**
     * Extract from multi-file entry
     *
     * @param array{
     *     name: list<string>,
     *     tmp_name: list<string>,
     *     type: list<string>,
     *     size: list<int>,
     *     error: list<int>
     * } $data
     */
    public static function fromMultiArray(array $data, int $index): self
    {
        return new self(
            name: $data['name'][$index],
            path: $data['tmp_name'][$index],
            type: $data['type'][$index],
            size: $data['size'][$index],
            error: $data['error'][$index],
        );
    }
}
