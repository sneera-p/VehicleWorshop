<?php

declare(strict_types=1);

namespace Vwork\Test\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Deptrac covers Layering rules
 *  1. Modules depends only on Shared and Infrastructure
 */
final class ModuleRules
{
    /**
     * Module structure:
     *  - IModuleFacade.php
     *  - ModuleFacade.php
     *  - Entity/
     *      - ThisEntity.php
     *      - ThatEntity.php
     *      - ...
     *  - Internal/
     *      - ModuleRepository.php
     *      - ThatInternal.php
     *      - ...
     */

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
    public function modules_dependant_only_on_listed_modules(): iterable
    {
        foreach ($this->moduleDeps as $name => $deps) {
            yield PHPat::rule()
                ->classes(Selector::inNamespace("/^Vwork\\\\Domain\\\\Modules\\\\{$name}($|\\\\)/", true))
                ->shouldNot()
                ->dependOn()
                ->classes(Selector::inNamespace('/^Vwork\\\\Domain\\\\Modules($|\\\\)/', true))
                ->excluding(...array_map(
                    fn ($dep) => Selector::inNamespace("/^Vwork\\\\Domain\\\\Modules\\\\{$dep}($|\\\\)/", true),
                    $deps
                ))
                ->because("{$name} module can only depend on " . implode(', ', $deps));
        }
    }

    /** @return iterable<Rule> */
    #[TestRule]
    public function facade_implementation_is_private(): iterable
    {
        foreach (array_keys($this->moduleDeps) as $name) {
            yield PHPat::rule()
                ->classes(Selector::inNamespace("/^Vwork($|\\\\)/", true))
                ->excluding(Selector::inNamespace("/^Vwork\\\\Domain\\\\Test($|\\\\)/", true))
                ->shouldNot()
                ->dependOn()
                ->classes(Selector::classname("/^Vwork\\\\Domain\\\\Modules\\\\{$name}Facade$/", true))
                ->because("Real Module implementation is Injected via configuration file (eg: web/config/services/modules.php)");
        }
    }

    /** @return iterable<Rule> */
    #[TestRule]
    public function facade_cannot_be_used_internaly(): iterable
    {
        foreach (array_keys($this->moduleDeps) as $name) {
            yield PHPat::rule()
                ->classes(Selector::inNamespace("/^Vwork\\\\Domain\\\\Modules\\\\{$name}\\\\(Entity|Internal)($|\\\\)/", true))
                ->shouldNot()
                ->dependOn()
                ->classes(Selector::classname("/^Vwork\\\\Domain\\\\Modules\\\\(I|){$name}Facade$/", true))
                ->because("Can't use the Facade internaly, That's Stupid");
        }
    }

    /** @return iterable<Rule> */
    #[TestRule]
    public function facade_should_implement_interface(): iterable
    {
        foreach (array_keys($this->moduleDeps) as $name) {
            yield PHPat::rule()
                ->classes(Selector::classname("/^Vwork\\\\Domain\\\\Modules\\\\I{$name}Facade$/", true))
                ->should()
                ->extend()
                ->classes(Selector::classname("/^Vwork\\\\Domain\\\\Modules\\\\IFacade$/", true))
                ->because("every Facade interface must implement IFacade");
        }

        foreach (array_keys($this->moduleDeps) as $name) {
            yield PHPat::rule()

                ->classes(Selector::classname("/^Vwork\\\\Domain\\\\Modules\\\\{$name}Facade$/", true))
                ->should()
                ->implement()
                ->classes(Selector::classname("/^Vwork\\\\Domain\\\\Modules\\\\I{$name}Facade$/", true))
                ->because("Dude the entire point is for {$name}Facade to implement I{$name}Facade");
        }
    }

    /** @return iterable<Rule> */
    #[TestRule]
    public function entities_are_readonly(): iterable
    {
        foreach (array_keys($this->moduleDeps) as $name) {
            yield PHPat::rule()
                ->classes(Selector::inNamespace("/^Vwork\\\\Domain\\\\Modules\\\\{$name}\\\\Entity($|\\\\)/", true))
                ->should()
                ->beReadonly()
                ->because("Entity classes are immutable");
        }
    }

    /** @return iterable<Rule> */
    #[TestRule]
    public function entities_cannot_use_internal(): iterable
    {
        foreach (array_keys($this->moduleDeps) as $name) {
            yield PHPat::rule()
                ->classes(Selector::inNamespace("/^Vwork\\\\Domain\\\\Modules\\\\{$name}\\\\Entity($|\\\\)/", true))
                ->shouldNot()
                ->dependOn()
                ->classes(Selector::inNamespace("/^Vwork\\\\Domain\\\\Modules\\\\{$name}\\\\Internal($|\\\\)/", true))
                ->because("Entity classes at the disposal of Internal/, not the other way around");
        }
    }

    /** @return iterable<Rule> */
    #[TestRule]
    public function internals_are_private(): iterable
    {
        foreach (array_keys($this->moduleDeps) as $name) {
            yield PHPat::rule()
                ->classes(Selector::inNamespace("/^Vwork($|\\\\)/", true))
                ->excluding(Selector::inNamespace("/^Vwork\\\\Domain\\\\Modules\\\\{$name}($|\\\\)/", true))
                ->shouldNot()
                ->dependOn()
                ->classes(Selector::inNamespace("/^Vwork\\\\Domain\\\\Modules\\\\{$name}\\\\Internal($|\\\\)/", true))
                ->because("The internals of a module are encapsulated");
        }
    }
}
