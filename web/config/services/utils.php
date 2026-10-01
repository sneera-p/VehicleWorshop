<?php

declare(strict_types=1);

use Random\Randomizer;
use Vwork\Web\Registry\IAppRegistry;
use Vwork\Web\Utils\CsrfToken;
use Vwork\Web\Utils\View;

return [
    View::class => static fn (IAppRegistry $r) => new View(
        dir: 'web/resources/views'
    ),
    CsrfToken::class => static fn (IAppRegistry $r) => new CsrfToken(
        secret: 'secret',
        random: new Randomizer()
    )
];
