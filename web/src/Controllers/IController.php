<?php

declare(strict_types=1);

namespace Vwork\Web\Controllers;

/**
 * Marks a class as a controller.
 *
 * It has no methods. It only lets the registry and the pipeline say
 * "this must be a controller" in their types.
 *
 * @phpstan-type StringMap array<string, mixed>
 *
 * @author Senira <senirahan@gmail.com>
 */
interface IController
{
}
