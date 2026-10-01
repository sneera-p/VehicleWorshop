<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Utils;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vwork\Web\Utils\View;
use Vwork\Web\WebError;

final class ViewTest extends TestCase
{
    private const string DIR = 'web/test/Fixtures/Views';

    /**
     * @param array<string, mixed> $data
     */
    #[Test]
    #[TestWith(['hello', [], "Hello\n"])]
    #[TestWith(['hello', ['unused' => 1], "Hello\n"])]
    #[TestWith(['vars', ['name' => 'Sneze', 'count' => 3], "Sneze has 3 jobs\n"])]
    #[TestWith(['zero', [], "0\n"])]
    public function renders_templates_with_data_as_locals(string $template, array $data, string $expected): void
    {
        $level = ob_get_level();

        $this->assertSame($expected, new View(self::DIR)->render($template, $data));
        $this->assertSame($level, ob_get_level());
    }

    #[Test]
    public function a_template_can_render_another_view_it_was_given(): void
    {
        $view = new View(self::DIR);

        $this->assertSame("[Hello\n]\n", $view->render('nested', ['view' => $view]));
    }

    #[Test]
    public function templates_cannot_see_the_view_itself(): void
    {
        $this->assertSame("hidden", new View(self::DIR)->render('this'));
    }

    #[Test]
    public function data_cannot_redirect_which_file_is_required(): void
    {
        // EXTR_SKIP: $__path is already set, so this key is ignored
        $data = ['__path' => __DIR__ . '/../../Fixtures/escape.php'];

        $this->assertSame("Hello\n", new View(self::DIR)->render('hello', $data));
    }

    #[Test]
    public function a_failing_template_throws_web_error_and_leaves_no_output_behind(): void
    {
        $level = ob_get_level();

        try {
            new View(self::DIR)->render('throws');
            $this->fail('Expected WebError');
        } catch (WebError $e) {
            $this->assertStringContainsString('throws', $e->getMessage());
            $this->assertInstanceOf(RuntimeException::class, $e->getPrevious());
        }

        $this->assertSame($level, ob_get_level());
        $this->expectOutputString(''); // the half page before the throw is gone
    }

    #[Test]
    #[TestWith(['web/test/Fixtures/does_not_exist'])]
    #[TestWith(['web/test/Fixtures/Views/hello.php'])] // a file, not a dir
    public function constructor_throws_unless_given_a_directory(string $dir): void
    {
        $this->expectException(WebError::class);
        new View($dir);
    }

    #[Test]
    #[TestWith(['does_not_exist'])]
    #[TestWith(['hello.php'])]
    #[TestWith([''])]
    #[TestWith(['../escape'])] // exists, but outside the views dir
    public function throws_for_templates_that_are_missing_or_outside_the_views_dir(string $template): void
    {
        $this->expectException(WebError::class);
        new View(self::DIR)->render($template);
    }
}
