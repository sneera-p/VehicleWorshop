<?php

declare(strict_types=1);

namespace Vwork\Web\Utils;

use Vwork\Web\WebError;

final class View
{
    /**
     * Reads Views folder (relative to root of project) from ENV
     *
     * @return string absolute path of Views folder
     * @throws WebError if ENV variable VIEW_PATH is not set
     */
    private static function dir(): string
    {
        $path = getenv('VIEW_PATH');

        if ($path === false || $path === '') {
            throw new WebError('VIEW_PATH is not set correctly');
        }

        $pruned = realpath(__DIR__ . '/../../../' . $path);

        if ($pruned === false) {
            throw new WebError("Path $path does not exist");
        }

        return $pruned;
    }

    /**
     * Renders a view file to a string. $data becomes local variables
     * inside the template.
     *
     // Be very careful with what you pass as $data, because it's accessible to the view
     *
     * @param array<string, mixed> $data
     * @throws WebError if the template doesn't exist
     */
    public static function render(string $template, array $data = []): string
    {
        $dir = self::dir() . '/';
        $path = realpath($dir . $template . '.php');
        if ($path === false || !str_starts_with($path, $dir) || !is_file($path) || !is_readable($path)) {
            throw new WebError("View not found: {$template}");
        }

        ob_start();
        extract($data, EXTR_SKIP);
        require $path;
        return (string) ob_get_clean();
    }
}
