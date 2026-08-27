<?php

declare(strict_types=1);

namespace Vwork\Test\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class InternalNamespaceRule
{
    #[TestRule]
    public function internal_is_never_reachable_from_web_worker_or_console(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::classname('/^Vwork\\\\(Web|Worker|Console)\\\\/', true),
            )
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::classname('/^Vwork\\\\Domain\\\\(Infrastructure|Modules)\\\\.+\\\\Internal\\\\/', true),
            )
            ->because('Internal/ is private to the module or infrastructure piece that owns it — Web/Worker/Console may only reach it through a facade or IInfrastructure implementation, never directly.');
    }
}

