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
            throw new WebError('VIEW_PATH is not set');
        }

        return __DIR__ . '/../../../' . $path;
    }

    /**
     * Renders a view file to a string. $data becomes local variables
     * inside the template.
     *
     * @param array<string, mixed> $data
     * @throws WebError if the template doesn't exist
     */
    public static function render(string $template, array $data = []): string
    {
        $path = realpath(self::dir() . '/' . $template . '.php');
        if ($path === false || !is_file($path) || !is_readable($path)) {
            throw new WebError("View not found: {$template}");
        }

        ob_start();
        extract($data, EXTR_SKIP);
        require $path;
        return (string) ob_get_clean();
    }
}
