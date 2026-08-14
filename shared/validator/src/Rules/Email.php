<?php

declare(strict_types=1);

namespace Vwork\Shared\Validator\Rules;

use Vwork\Shared\Validator\Rule;

final class Email implements Rule
{
    public function passes(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
}
