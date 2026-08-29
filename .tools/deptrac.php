<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\ClassLikeConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

return static function (DeptracConfig $config): void {
    $config
        ->paths(
            '../shared',
            '../domain',
            '../web',
            '../worker',
            '../console',
            '../test',
        )
        ->cacheFile('.var/cache/deptrac/deptrac.cache')
        ->excludeFiles(
            '#.*/vendor/.*#',
            '#.*/node_modules/.*#',
        )
        ->layers(
            // shared/* — zero domain knowledge, zero transport knowledge.
            $shared = Layer::withName('Shared')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Shared\\\\.*'),
            ),

            // domain/infrastructure — one package, IInfrastructure + every
            // concrete Database/PubSub/Cache/Notification implementation.
            $infrastructure = Layer::withName('Infrastructure')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Domain\\\\Infrastructure\\\\.*'),
            ),

            // domain/modules — one package, every module's facade + entity
            // + Internal/. Cross-module access is a code-review discipline,
            // not something Composer enforces (all modules share one
            // package) — this layer is what actually enforces it.
            $modules = Layer::withName('Modules')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Domain\\\\Modules\\\\.*'),
            ),

            // web/ — the only HTTP-facing process. IController, IMiddleware,
            // IPipelineHandler, IRouter, Request/Response, and everything
            // under them only ever have ONE consumer, so they live here,
            // not in shared/ — see README's "Where the decisions came from".
            $web = Layer::withName('Web')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Web\\\\.*'),
            ),

            // worker/ — QueueWorker. No HTTP at all, no IController/IMiddleware.
            $worker = Layer::withName('Worker')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Worker\\\\.*'),
            ),

            // console/ — one-off CLI tooling. Shares worker/'s container at
            // runtime, but is its own package with its own bindings — never
            // assumes worker/'s bindings happen to cover what it needs.
            $console = Layer::withName('Console')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Console\\\\.*'),
            ),

            // Unit tests live inside each package's own tests/, namespaced
            // to match that package, always ending in \Test\Unit\... — e.g.
            // Vwork\Shared\Collections\Test\Unit\TrieNodeTest.
            //
            // Exception: domain/'s unit tests are NOT split per-package
            // (infrastructure/ vs modules/ share one domain/tests/ folder,
            // per the domain/composer.json decision) — so instead of
            // Vwork\Domain\Infrastructure\Test\Unit\... and
            // Vwork\Domain\Modules\Test\Unit\...,
            $sharedUnit = Layer::withName('SharedUnit')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Shared\\\\.+\\\\Test\\\\Unit\\\\.*'),
            ),
            $domainUnit = Layer::withName('DomainUnit')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Domain\\\\Test\\\\Unit\\\\(Infrastructure|Modules)\\\\.*'),
            ),
            $webUnit = Layer::withName('WebUnit')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Web\\\\Test\\\\Unit\\\\.*'),
            ),
            $workerUnit = Layer::withName('WorkerUnit')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Worker\\\\Test\\\\Unit\\\\.*'),
            ),
            $consoleUnit = Layer::withName('ConsoleUnit')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Console\\\\Test\\\\Unit\\\\.*'),
            ),

            // domain/test/integration — real Postgres + Valkey, infrastructure
            // and modules exercised together. Lives at domain/'s own root,
            // not inside infrastructure/ or modules/, since it's testing
            // the seam between them, which neither owns alone.
            $domainIntegration = Layer::withName('DomainIntegration')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Domain\\\\Test\\\\Integration\\\\.*'),
            ),

            // web/test/integration — real Postgres/Valkey, raw HTTP client,
            // no browser. Deepest single-process check web/ has short of
            // Playwright.
            $webIntegration = Layer::withName('WebIntegration')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Web\\\\Test\\\\Integration\\\\.*'),
            ),

            // worker/test/integration — real Valkey, does QueueWorker
            // actually react to a published message. The deepest test
            // worker/ has; there's no third tier here, unlike web/'s e2e.
            $workerIntegration = Layer::withName('WorkerIntegration')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Worker\\\\Test\\\\Integration\\\\.*'),
            ),

            // console/test/integration — real Postgres, does MigrateCommand
            // actually produce the right schema. Same reasoning as worker/ —
            // no third tier.
            $consoleIntegration = Layer::withName('ConsoleIntegration')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Console\\\\Test\\\\Integration\\\\.*'),
            ),

            // test/load, test/architecture, test/security — whole-repo
            // tooling, not app tests.
            $load = Layer::withName('Load')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Test\\\\Load\\\\.*'),
            ),
            $architecture = Layer::withName('Architecture')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Test\\\\Architecture\\\\.*'),
            ),
        )
        ->rulesets(
            // shared/ depends on nothing else in the codebase.
            Ruleset::forLayer($shared),

            // infrastructure/ and modules/ each depend on domain/'s root
            // (for IDomainRegistry/IFacade) and shared/ — never on each
            // other directly, and never on web/, worker/, or console/.
            Ruleset::forLayer($infrastructure)->accesses($shared),
            Ruleset::forLayer($modules)->accesses($infrastructure, $shared),

            // web/, worker/, console/ all depend on domain/'s root,
            // infrastructure/, and modules/ (reached only through
            // IDomainRegistry — see README's registry split), plus shared/.
            // None of them may depend on each other.
            Ruleset::forLayer($web)->accesses($modules, $shared),
            Ruleset::forLayer($worker)->accesses($modules, $shared),
            Ruleset::forLayer($console)->accesses($modules, $shared),

            // Unit tests exercise whatever they sit next to — any production layer, never another test layer.
            Ruleset::forLayer($sharedUnit)->accesses($shared),
            Ruleset::forLayer($domainUnit)->accesses($shared, $infrastructure, $modules),
            Ruleset::forLayer($webUnit)->accesses($shared, $infrastructure, $modules, $web),
            Ruleset::forLayer($workerUnit)->accesses($shared, $infrastructure, $modules, $worker),
            Ruleset::forLayer($consoleUnit)->accesses($shared, $infrastructure, $modules, $console),

            // Unit tests exercise whatever they sit next to — exercising real externals
            // together.
            Ruleset::forLayer($domainIntegration)->accesses($shared, $infrastructure, $modules),
            Ruleset::forLayer($webIntegration)->accesses($shared, $infrastructure, $modules, $web),
            Ruleset::forLayer($workerIntegration)->accesses($shared, $infrastructure, $modules, $worker),
            Ruleset::forLayer($consoleIntegration)->accesses($shared, $infrastructure, $modules, $console),

            // Load/Architecture tooling inspects production code but isn't
            // itself part of it — same access as Unit.
            Ruleset::forLayer($load)->accesses($shared, $infrastructure, $modules, $web, $worker, $console),
            Ruleset::forLayer($architecture)->accesses($shared, $infrastructure, $modules, $web, $worker, $console),
        )
    ;
};
