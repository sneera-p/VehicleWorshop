<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Utils;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vwork\Web\Utils\View;
use Vwork\Web\WebError;

final class ViewTest extends TestCase
{
    private string|false $previousViewPath;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->previousViewPath = getenv('VIEW_PATH');
        putenv('VIEW_PATH=web/test/Fixtures/Views');
    }

    #[Override]
    protected function tearDown(): void
    {
        putenv($this->previousViewPath === false ? 'VIEW_PATH' : "VIEW_PATH={$this->previousViewPath}");
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $data
     */
    #[Test]
    #[TestWith(['hello', [], 'Hello' . "\n"])]
    #[TestWith(['hello', ['unused' => 1], 'Hello' . "\n"])]
    #[TestWith(['vars', ['name' => 'Sneze', 'count' => 3], 'Sneze has 3 jobs' . "\n"])]
    #[TestWith(['nested', [], "[Hello\n]\n"])]
    #[TestWith(['zero', [], '0' . "\n"])]
    #[TestWith(['shadow', ['message' => 'mine'], 'Hello Sailor!'])]
    public function renders_templates_with_data_as_locals(string $template, array $data, string $expected): void
    {
        $level = ob_get_level();

        $this->assertSame($expected, View::render($template, $data));
        $this->assertSame($level, ob_get_level());
    }

    #[Test]
    #[TestWith([null])]
    #[TestWith([''])]
    public function throws_when_view_path_is_unset_or_empty(?string $value): void
    {
        putenv($value === null ? 'VIEW_PATH' : "VIEW_PATH={$value}");

        $this->expectException(WebError::class);
        View::render('hello');
    }

    #[Test]
    #[TestWith(['does_not_exist'])]
    #[TestWith(['hello.php'])]
    #[TestWith([''])]
    #[TestWith(['../escape'])] // exists, but outside VIEW_PATH
    public function throws_for_templates_that_are_missing_or_outside_the_views_dir(string $template): void
    {
        $this->expectException(WebError::class);
        View::render($template);
    }
}
