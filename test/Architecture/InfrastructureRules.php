<?php

declare(strict_types=1);

namespace Vwork\Test\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Covers the extra rules in Infrastructure that Deptrac cannot
 *
 * Deptrac covers Layering rules
 *  1. Infrastructure depends only on Shared
 *
 * @author Senira <senirahan@gmail.com>
 */
final class InfrastructureRules
{
    #[TestRule]
    public function infrastructure_implementation_is_private(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace("/^Vwork($|\\\\)/", true))
            ->excluding(Selector::inNamespace("/^Vwork\\\\Domain\\\\Test($|\\\\)/", true))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::classname("/^Vwork\\\\Domain\\\\Infrastructure($|\\\\)/", true), Selector::isStandardClass())
            ->because("Real Module implementation is Injected via configuration file (eg: web/config/services/modules.php)");
    }

    #[TestRule]
    public function internals_are_private(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace("/^Vwork($|\\\\)/", true))
            ->excluding(Selector::inNamespace("/^Vwork\\\\Domain\\\\Infrastructure($|\\\\)/", true))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace("/^Vwork\\\\Domain\\\\Infrastructure\\\\Internal($|\\\\)/", true))
            ->because("Infrastructure's internals (e.g. the Valkey base class) are encapsulated");
    }
}
