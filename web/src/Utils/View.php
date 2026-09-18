<?php

declare(strict_types=1);

namespace Vwork\Web\Utils;

use Throwable;
use Vwork\Web\WebError;

final class View
{
    private const viewDir = __DIR__ . '/../../resources/views';

    /**
     * Renders a view file to a string. $data becomes local variables
     * inside the template.
     *
     * @param array<string, mixed> $data
     * @throws WebError if the template doesn't exist
     */
    public static function render(string $template, array $data = []): string
    {
        $path = realpath(self::viewDir . '/' . $template . '.php');

        if ($path === false) {
            throw new WebError("View not found: {$template}");
        }

        ob_start();
        try {
            require $path;
        } catch (Throwable $e) {
            ob_end_clean();   // don't leak a half-rendered buffer
            throw $e;
        }

        return ob_get_clean() ?: '';
    }
}
