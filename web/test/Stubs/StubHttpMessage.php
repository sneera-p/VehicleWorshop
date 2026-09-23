<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Stubs;

use Override;
use Vwork\Web\Http\HttpMessage;
use Vwork\Web\Http\HttpCookies;

final class StubHttpMessage extends HttpMessage
{
    /**
     * @var array<value-of<HttpCookies>, string>
     */
    #[Override]
    public array $cookies { get => []; }

    public function __construct(array $headers = [])
    {
        parent::__construct($headers);
    }
}
