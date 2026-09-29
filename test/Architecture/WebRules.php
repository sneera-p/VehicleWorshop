<?php

declare(strict_types=1);

namespace Vwork\Test\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Deptrac covers Layering rules
 *  1. Web depends only on Modules & Shared
 *
 * This file covers what Deptrac can't see inside web/src/:
 *
 *  1. Concrete Controllers/Middleware are private — only config injects them.
 *  2. The composition root (IApp/IAppBuilder/WebApp/AppBuilder/IHttpRegistry/
 *     IServiceRegistry/AppServiceRegistry — all sitting directly at
 *     Vwork\Web\* with no subnamespace) is invisible to every subfolder.
 *     It wires them; they never reach back up at it.
 *
 * @author Senira <senirahan@gmail.com>
 */
final class WebRules
{
    #[TestRule]
    public function controllers_are_private(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace("/^Vwork($|\\\\)/", true))
            ->excluding(Selector::inNamespace("/^Vwork\\\\Web\\\\Test($|\\\\)/", true))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::classname("/^Vwork\\\\Web\\\\Controllers($|\\\\)/", true),
                Selector::isFinal(),
                Selector::isAbstract(),
            )
            ->because("Only Configuration can Inject Controllers, everyone else uses IController");
    }

    #[TestRule]
    public function middlware_are_private(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace("/^Vwork($|\\\\)/", true))
            ->excluding(Selector::inNamespace("/^Vwork\\\\Web\\\\Test($|\\\\)/", true))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::classname("/^Vwork\\\\Web\\\\Middleware($|\\\\)/", true),
                Selector::isFinal(),
            )
            ->because("Only Configuration can Inject Middleware, everyone else uses IMiddleware");
    }
}
