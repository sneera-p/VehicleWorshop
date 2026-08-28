<?php

declare(strict_types=1);

namespace Vwork\Test\Architecture\Domain;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Deptrac covers Layering rules
 *  1. Infrastructure depends only on Shared
 */
final class InfrastructureRule
{
    #[TestRule]
    public function internal_is_never_reachable_externaly(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('/^Vwork($|\\\\)/', true))
            ->excluding(Selector::inNamespace('/^Vwork\\\\Domain\\\\Infrastructure\\\\Internal($|\\\\)/', true))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace('/^Vwork\\\\Domain\\\\Infrastructure\\\\Internal($|\\\\)/', true))
            ->because('Infrastructure must not expose it\'s internals');
    }
}
