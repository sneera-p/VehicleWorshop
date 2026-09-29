<?php

declare(strict_types=1);

namespace Vwork\Test\Architecture;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Vwork\Web\Controllers\ControllerAction;
use Vwork\Web\Http\Request;
use Vwork\Web\Http\Response;

/**
 * Every #[ControllerAction] must be:
 * public function name(Request $request, array $attr): Response
 *
 * @implements Rule<ClassMethod>
 */
final class ControllerActionRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!self::isMarked($node)) {
            return [];
        }

        $params = $node->params;
        $ok = $node->isPublic()
            && !$node->isStatic()
            && count($params) === 2
            && self::is($params[0]->type, Request::class)
            && self::is($params[1]->type, 'array')
            && self::is($node->returnType, Response::class);

        if ($ok) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                '#[ControllerAction] %s() must be: public function %s(Request $request, array $attr): Response',
                $node->name->toString(),
                $node->name->toString(),
            ))->identifier('vwork.controllerAction')->build(),
        ];
    }

    private static function isMarked(ClassMethod $node): bool
    {
        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if ($attr->name->toString() === ControllerAction::class) {
                    return true;
                }
            }
        }
        return false;
    }

    /** One exact type: not nullable, not a union. */
    private static function is(?Node $type, string $name): bool
    {
        return ($type instanceof Name || $type instanceof Identifier)
            && $type->toString() === $name;
    }
}
