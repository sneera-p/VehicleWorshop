<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Web\Http\UploadedFile;

final class UploadedFileTest extends TestCase
{
    /**
     * @param array{
     *     name: string,
     *     tmp_name: string,
     *     type: string,
     *     size: int,
     *     error: int
     * } $data
     */
    #[Test]
    #[TestWith([[
        'name' => 'nnn',
        'tmp_name' => 'ttt',
        'type' => 't',
        'size' => 16,
        'error' => UPLOAD_ERR_OK
    ]])]
    public function single_array_parse(array $data): void
    {
        $file = UploadedFile::fromSingleArray($data);

        $this->assertEquals($data['name'], $file->name);
        $this->assertEquals($data['tmp_name'], $file->path);
        $this->assertEquals($data['type'], $file->type);
        $this->assertEquals($data['size'], $file->size);
        $this->assertEquals($data['error'], $file->error);
    }

    /**
     * @param array{
     *     name: list<string>,
     *     tmp_name: list<string>,
     *     type: list<string>,
     *     size: list<int>,
     *     error: list<int>
     * } $data
     */
    #[Test]
    #[TestWith([[
        'name' => ['nnn', 'mmm'],
        'tmp_name' => ['ttt', 'sss'],
        'type' => ['t', 'u'],
        'size' => [16, 17],
        'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_PARTIAL]
    ]])]
    public function multi_array_parse(array $data): void
    {
        foreach (array_keys($data['name']) as $index) {
            $file = UploadedFile::fromMultiArray($data, $index);

            $this->assertEquals($data['name'][$index], $file->name);
            $this->assertEquals($data['tmp_name'][$index], $file->path);
            $this->assertEquals($data['type'][$index], $file->type);
            $this->assertEquals($data['size'][$index], $file->size);
            $this->assertEquals($data['error'][$index], $file->error);
        }
    }
}
