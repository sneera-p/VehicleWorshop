<?php

declare(strict_types=1);

namespace Vwork\Web\Controllers;

use Attribute;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use Vwork\Web\Http\Request;
use Vwork\Web\Http\Response;

/**
 * Marks a controller method as a route target.
 *
 * Every action has the same shape:
 *
 * ```php
 * #[ControllerAction]
 * public function name(Request $request, array $attr): Response
 * ```
 *
 * The mark does nothing by itself. verify() reads it when a route is
 * built, so a wrong method fails at boot, not on a user's request.
 *
 * @author Senira <senirahan@gmail.com>
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class ControllerAction
{
    /**
     * @throws ControllerError if $method is not a marked action with the right shape
     */
    public static function verify(IController $controller, string $method): void
    {
        $problem = self::problem($controller, $method);

        if ($problem !== null) {
            throw new ControllerError("{$method}() {$problem}", $controller);
        }
    }

    /**
     * @return string|null what is wrong, or null if the method is a valid action
     */
    private static function problem(IController $controller, string $method): ?string
    {
        if (!method_exists($controller, $method)) {
            return 'does not exist';
        }

        $ref = new ReflectionMethod($controller, $method);

        if ($ref->getAttributes(self::class) === []) {
            return 'is not marked #[ControllerAction]';
        }

        if (!$ref->isPublic() || $ref->isStatic()) {
            return 'must be public and not static';
        }

        $params = $ref->getParameters();
        if (
            count($params) !== 2
            || !self::is($params[0]->getType(), Request::class)
            || !self::is($params[1]->getType(), 'array')
        ) {
            return 'must take (Request $request, array $attr)';
        }

        if (!self::is($ref->getReturnType(), Response::class)) {
            return 'must return Response';
        }

        return null;
    }

    /** One exact type, not nullable, not a union. */
    private static function is(?ReflectionType $type, string $name): bool
    {
        return $type instanceof ReflectionNamedType
            && !$type->allowsNull()
            && $type->getName() === $name;
    }
}
