<?php

declare(strict_types=1);

namespace Vwork\Test\Architecture\Domain;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Deptrac covers Layering rules
 *  1. Modules depends only on Shared and Infrastructure
 */
final class ModuleRule
{
    /** @var array<string,list<string>> */
    private array $moduleDeps = [
        'SystemConfig' => [],
        'Staff' => [],
        'CustomerVehicle' => [],
        'Identity' => ['SystemConfig'],
        'Appointment' => ['CustomerVehicle', 'Staff'],
        'Job' => ['Staff', 'Billing', 'CustomerVehicle', 'Inventory'],
        'Billing' => [],
        'Inventory' => ['Supplier'],
        'Supplier' => ['Staff'],
        'Notification' => ['SystemConfig'],
    ];

    /** @return iterable<Rule> */
    #[TestRule]
    public function internal_is_never_reachable_externaly(): iterable
    {
        foreach ($this->moduleDeps as $name => $deps) {
            yield PHPat::rule()
                ->classes(Selector::inNamespace('/^Vwork($|\\\\)/', true))
                ->excluding(Selector::inNamespace('/^Vwork\\\\Domain\\\\Modules\\\\'. $name .'\\\\Internal($|\\\\)/', true))
                ->shouldNot()
                ->dependOn()
                ->classes(Selector::inNamespace('/^Vwork\\\\Domain\\\\Modules\\\\'. $name .'\\\\Internal($|\\\\)/', true))
                ->because($name . ' must not expose it\'s internals');
        }
    }

    /** @return iterable<Rule> */
    #[TestRule]
    public function module_can_depend_only_on(): iterable
    {
        foreach ($this->moduleDeps as $name => $deps) {
            yield PHPat::rule()
                ->classes(Selector::inNamespace('/^Vwork\\\\Domain\\\\Modules\\\\'. $name .'($|\\\\)/', true))
                ->shouldNot()
                ->dependOn()
                ->classes(Selector::inNamespace('/^Vwork\\\\Domain\\\\Modules($|\\\\)/', true))
                ->excluding(...array_map(
                    fn ($dep) => Selector::inNamespace('/^Vwork\\\\Domain\\\\Modules\\\\'. $dep .'($|\\\\)/', true),
                    $deps
                ))
                ->because($name . ' must only depend on modules ' . implode(', ', $deps));
        }
    }
}
