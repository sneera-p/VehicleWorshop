<?php

declare(strict_types=1);

namespace Vwork\Test\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;
use Throwable;

/**
 * Covers the extra rules in Infrastructure that Deptrac cannot
 *
 * Deptrac covers Layering rules
 *  1. Infrastructure depends only on Shared
 *
 * @author Senira <senirahan@gmail.com>
 */
final class DomainRules
{
    #[TestRule]
    public function services_are_readonly(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::inNamespace('Vwork\Domain\Infrastructure'),
                Selector::AllOf(
                    Selector::inNamespace('Vwork\Domain\Modules'),
                    Selector::classname('/Facade$/', true),
                ),
            )
            ->excluding(
                Selector::isInterface(),
                Selector::isEnum(),
                Selector::implements(Throwable::class), // Error and Exception subclasses, in any selected namespace
            )
            ->should()
            ->beReadonly()
            ->because('worker mode reuses services across requests; mutable state leaks between them');
    }
}
