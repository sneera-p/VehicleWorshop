<?php

declare(strict_types=1);

namespace Vwork\Validator\Rules;

use Vwork\Validator\Rule;

final class Email implements Rule
{
    public function passes(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
}
