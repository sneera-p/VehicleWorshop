<?php

declare(strict_types=1);

namespace Vwork\Web\Utils;

use Throwable;
use Vwork\Web\WebError;

/**
 * Turns a template file into a string of HTML.
 * It knows nothing about HTTP; a controller wraps the string in a Response.
 *
 * @author Senira <senirahan@gmail.com>
 */
final readonly class View implements IUtility
{
    private string $dir;

    /**
     * @param string $dir path to view templates, relative to the project root
     * @throws WebError if $dir isn't a directory
     */
    public function __construct(string $dir)
    {
        $real = realpath(__DIR__ . '/../../../' . $dir);
        if ($real === false || !is_dir($real)) {
            throw new WebError("Invalid path to view templates: {$dir}");
        }
        $this->dir = $real . '/';
    }

    /**
     * Renders a template to a string. Each key in $data becomes a local
     * variable inside the template, and that's all the template sees.
     *
     * @param array<string, mixed> $data
     * @throws WebError if the template doesn't exist or is outside the views folder
     */
    public function render(string $template, array $data = []): string
    {
        $path = realpath($this->dir . "{$template}.php");

        if ($path === false || !str_starts_with($path, $this->dir) || !is_file($path)) {
            throw new WebError("View not found: {$template}");
        }

        ob_start();
        try {
            // Closure to avoid leaking our locals to the view.
            (static function (string $__path, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__path;
            })($path, $data);
        } catch (Throwable $e) {
            ob_end_clean(); // don't leave half a page in the buffer
            throw new WebError("View $template render failed", $e);
        }

        return (string) ob_get_clean();
    }
}
