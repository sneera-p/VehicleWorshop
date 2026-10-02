<?php

declare(strict_types=1);

use Random\Randomizer;
use Vwork\Shared\Collections\IRegistry;
use Vwork\Web\Utils\CsrfToken;
use Vwork\Web\Utils\View;

return [
    View::class => static fn (IRegistry $r) => new View(
        dir: 'web/resources/views'
    ),
    CsrfToken::class => static fn (IRegistry $r) => new CsrfToken(
        secret: 'secret',
        random: new Randomizer()
    )
];
