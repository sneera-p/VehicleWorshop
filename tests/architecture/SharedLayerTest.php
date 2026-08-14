<?php

declare(strict_types=1);

namespace Vwork\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;
use Vwork\Shared\Validator\Rule as ValidatorRule;

final class SharedLayerTest
{
    #[TestRule]
    public function validatorRulesMustImplementRuleInterface(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Vwork\\Shared\\Validator\\Rules'))
            ->should()
            ->implement()
            ->classes(Selector::classname(ValidatorRule::class))
            ->because('every validation rule must be usable wherever Rule is expected.');
    }

    #[TestRule]
    public function validatorRulesMustBeFinal(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('Vwork\\Shared\\Validator\\Rules'))
            ->should()
            ->beFinal()
            ->because('rules are single-purpose value objects, not meant to be extended.');
    }
}
