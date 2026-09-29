<?php

declare(strict_types=1);

namespace Vwork\Web\Utils;

use Vwork\Web\WebError;

/**
 * Turns a template file into a string of HTML.
 * It knows nothing about HTTP; a controller wraps the string in a Response.
 *
 * @author Senira <senirahan@gmail.com>
 */
final class View
{
    /**
     * Finds the views folder. VIEW_PATH is relative to the project root.
     *
     * The path goes through realpath(), so it has no "../" left in it.
     * render() needs that: it compares this path with the template's
     * real path, and the two must be written the same way.
     *
     * @return string absolute path of the views folder
     * @throws WebError if VIEW_PATH is not set or is not a folder
     */
    private static function dir(): string
    {
        $path = getenv('VIEW_PATH');

        if ($path === false || $path === '') {
            throw new WebError('VIEW_PATH is not set');
        }

        $dir = realpath(__DIR__ . '/../../../' . $path);

        if ($dir === false || !is_dir($dir)) {
            throw new WebError("VIEW_PATH is not a folder: {$path}");
        }

        return $dir;
    }

    /**
     * Renders a template to a string. Each key in $data becomes a local
     * variable inside the template.
     *
     * The template can see everything in $data, so pass only what it
     * needs to show.
     *
     * @param array<string, mixed> $data
     * @throws WebError if the template doesn't exist or is outside the views folder
     */
    public static function render(string $template, array $data = []): string
    {
        $dir = self::dir() . '/';
        $path = realpath($dir . $template . '.php');

        // The last check stops "../" tricks: after realpath(), a file
        // outside the views folder no longer starts with $dir.
        if ($path === false || !str_starts_with($path, $dir) || !is_file($path) || !is_readable($path)) {
            throw new WebError("View not found: {$template}");
        }

        ob_start();
        extract($data, EXTR_SKIP);
        require $path;
        return (string) ob_get_clean();
    }
}
