<?php

declare(strict_types=1);

namespace Vwork\Validator;

interface Rule
{
    /**
     * Summary of passes
     * @param string $value The raw input to check
     * @return bool True if $value satisfies this rule
     */
    public function passes(string $value): bool;
}
