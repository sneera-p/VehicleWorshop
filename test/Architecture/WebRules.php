<?php

declare(strict_types=1);

namespace Vwork\Test\Architecture\Web;

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
 *  3. A strict dependency ladder between the subfolders:
 *
 *        Utils/       -> (nothing)
 *        Http/        -> (nothing)
 *        Controllers/ -> Http, Utils
 *        Middleware/  -> Http, Utils
 *        Pipeline/    -> Controllers, Middleware, Http, Utils
 *        Router/      -> Pipeline, Controllers, Middleware, Http, Utils
 *
 *     Utils/ and Http/ are both leaves and mutually exclusive — neither
 *     may touch the other.
 */
final class WebRules
{
    /**
     * Subfolders in ladder order — each may depend only on those before
     * it. Position in this array IS the rule, so inserting a new rung
     * later can't silently leave a gap.
     *
     * @var list<string>
     */
    private array $ladder = [
        'Utils',
        'Http',
        'Controllers',
        'Middleware',
        'Pipeline',
        'Router',
    ];

    /**
     * Same-rung folders that must not reach sideways at each other.
     * Utils/Http are both leaves. Controllers/Middleware both sit above
     * Http+Utils but are peers — Pipeline is what composes them together,
     * neither composes the other.
     *
     * @var array<string,list<string>>
     */
    private array $peers = [
        'Utils' => ['Http'],
        'Http' => ['Utils'],
        'Controllers' => ['Middleware'],
        'Middleware' => ['Controllers'],
    ];

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

    /**
     * The root files compose the application — AppBuilder reads routes and
     * resolves controllers, AppServiceRegistry holds the bindings, WebApp
     * runs the request. Nothing they compose may know they exist.
     *
     * The target regex "Vwork\Web\<Name>" with no trailing backslash
     * matches ONLY the root files; Vwork\Web\Http\Request and friends have
     * a further segment, so they're untouched by this.
     *
     * @return iterable<Rule>
     */
    #[TestRule]
    public function composition_root_is_invisible_to_subfolders(): iterable
    {
        foreach ($this->ladder as $folder) {
            yield PHPat::rule()
                ->classes(Selector::inNamespace("/^Vwork\\\\Web\\\\{$folder}($|\\\\)/", true))
                ->shouldNot()
                ->dependOn()
                ->classes(Selector::classname("/^Vwork\\\\Web\\\\[A-Za-z]+$/", true))
                ->because("{$folder}/ is composed by the root (AppBuilder / WebApp / AppServiceRegistry) — it must never reach back up at it.");
        }
    }

    /**
     * The ladder. For each folder the forbidden set is everything at or
     * above its own rung, plus its peers — derived from position rather
     * than hand-listed per folder.
     *
     * Router/ sits at the top with nothing forbidden, so it yields no
     * rule at all rather than a vacuous one.
     *
     * @return iterable<Rule>
     */
    #[TestRule]
    public function subfolder_dependency_ladder(): iterable
    {
        foreach ($this->ladder as $i => $folder) {
            $peers = $this->peers[$folder] ?? [];

            $forbidden = array_merge(array_slice($this->ladder, $i), $peers);
            $forbidden = array_values(array_diff(array_unique($forbidden), [$folder]));

            if ($forbidden === []) {
                continue;
            }

            $allowed = array_values(array_diff(array_slice($this->ladder, 0, $i), $peers));

            yield PHPat::rule()
                ->classes(Selector::inNamespace("/^Vwork\\\\Web\\\\{$folder}($|\\\\)/", true))
                ->shouldNot()
                ->dependOn()
                ->classes(...array_map(
                    fn (string $other) => Selector::inNamespace("/^Vwork\\\\Web\\\\{$other}($|\\\\)/", true),
                    $forbidden,
                ))
                ->because("{$folder}/ may only depend on " . ($allowed === [] ? 'nothing' : implode(', ', $allowed)));
        }
    }
}
