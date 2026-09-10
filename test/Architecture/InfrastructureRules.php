<?php

declare(strict_types=1);

namespace Vwork\Test\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Deptrac covers Layering rules
 *  1. Infrastructure depends only on Shared
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
}
