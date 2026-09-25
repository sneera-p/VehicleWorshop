<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Web\Http\UploadedFile;

final class UploadedFileTest extends TestCase
{
    private const array MULTI = [
        'name' => ['a.jpg', 'b.pdf'],
        'tmp_name' => ['/tmp/a', '/tmp/b'],
        'type' => ['image/jpeg', 'application/pdf'],
        'size' => [16, 17],
        'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_PARTIAL],
    ];

    #[Test]
    public function from_single_array_maps_every_field(): void
    {
        $file = UploadedFile::fromSingleArray([
            'name' => 'a.jpg',
            'tmp_name' => '/tmp/a',
            'type' => 'image/jpeg',
            'size' => 16,
            'error' => UPLOAD_ERR_OK,
        ]);

        $this->assertSame(
            ['a.jpg', '/tmp/a', 'image/jpeg', 16, UPLOAD_ERR_OK],
            [$file->name, $file->path, $file->type, $file->size, $file->error],
        );
    }

    /**
     * @param array{
     *  0: string,
     *  1: string,
     *  2: string,
     *  3: int,
     *  4: int
     * } $expected
     */
    #[Test]
    #[TestWith([0, ['a.jpg', '/tmp/a', 'image/jpeg', 16, UPLOAD_ERR_OK]])]
    #[TestWith([1, ['b.pdf', '/tmp/b', 'application/pdf', 17, UPLOAD_ERR_PARTIAL]])]
    public function from_multi_array_picks_one_index_from_every_field(int $index, array $expected): void
    {
        $file = UploadedFile::fromMultiArray(self::MULTI, $index);

        $this->assertSame($expected, [$file->name, $file->path, $file->type, $file->size, $file->error]);
    }
}
