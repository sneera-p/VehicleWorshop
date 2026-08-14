<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\ClassLikeConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

return static function (DeptracConfig $config): void {
    $config
        ->paths(
            '../app',
            '../modules',
            '../shared',
            '../public',
            '../tests',
        )
        ->cacheFile('var/cache/deptrac/deptrac.cache')
        ->excludeFiles(
            '#.*/vendor/.*#',
        )
        ->layers(
            // public/index.php - the single HTTP entrypoint.
            $public = Layer::withName('Public')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Public\\\\.*'),
            ),

            // Application code (Vwork\App\...), including Vwork\App\Config\...
            // (routes.php, services.php) - config lives under App, so it's
            // only ever reachable through the App layer.
            $app = Layer::withName('App')->collectors(
                ClassLikeConfig::create('^Vwork\\\\App\\\\.*'),
            ),

            // Feature modules (modules/*), namespaced under Vwork\Modules\...
            $modules = Layer::withName('Modules')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Modules\\\\.*'),
            ),

            // shared/kernel + shared/validator - pure PHP, no framework deps,
            // usable by any module.
            $shared = Layer::withName('Shared')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Shared\\\\.*'),
            ),

            // Unit tests live next to the source they test (e.g.
            // shared/validator/tests, modules/*/tests), namespaced as
            // <package-namespace>\Tests\..., not under a single Vwork\Tests\Unit
            // root - this matches each package's own composer.json
            // autoload-dev entry.
            $unit = Layer::withName('Unit')->collectors(
                ClassLikeConfig::create('^Vwork\\\\App\\\\Tests\\\\.*'),
                ClassLikeConfig::create('^Vwork\\\\Modules\\\\.+\\\\Tests\\\\.*'),
                ClassLikeConfig::create('^Vwork\\\\Shared\\\\.+\\\\Tests\\\\.*'),
            ),

            // tests/integration - cross-module, needs a running DB/Valkey.
            $integration = Layer::withName('Integration')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Tests\\\\Integration\\\\.*'),
            ),

            // tests/e2e - black-box, drives the app the same way a real
            // client would.
            $e2e = Layer::withName('E2e')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Tests\\\\E2e\\\\.*'),
            ),

            // tests/architecture - phpat rule definitions, not app tests.
            // Only ever references phpat itself plus whatever classes a
            // given rule inspects.
            $architecture = Layer::withName('Architecture')->collectors(
                ClassLikeConfig::create('^Vwork\\\\Tests\\\\Architecture\\\\.*'),
            ),
        )
        ->rulesets(
            // public/index.php only bootstraps the App layer.
            Ruleset::forLayer($public)->accesses($app),

            // App (including its Config sub-namespace) can use modules and
            // shared, since config wires routes/services across both.
            Ruleset::forLayer($app)->accesses($modules, $shared),

            // Modules can use shared, but never each other or App directly
            // (enforced by omission below).
            Ruleset::forLayer($modules)->accesses($shared),

            // Shared has no dependencies on the rest of the codebase.
            Ruleset::forLayer($shared),

            // Unit tests exercise whatever layer they sit next to, so they
            // may reach into any production layer - but never into other
            // test layers.
            Ruleset::forLayer($unit)->accesses($public, $app, $modules, $shared),

            // Integration tests are cross-cutting by definition.
            Ruleset::forLayer($integration)->accesses($public, $app, $modules, $shared),

            // E2e tests drive the app as a black box - through the Public
            // entrypoint only, not by reaching into App/Modules/Shared
            // internals directly.
            Ruleset::forLayer($e2e)->accesses($public),

            // Architecture rule definitions inspect production code but
            // aren't tests of it - same access as Unit.
            Ruleset::forLayer($architecture)->accesses($public, $app, $modules, $shared),
        )
    ;
};
