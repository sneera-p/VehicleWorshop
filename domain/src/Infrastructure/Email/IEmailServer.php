<?php

declare(strict_types=1);

namespace Vwork\Domain\Infrastructure\Email;

use Vwork\Domain\Infrastructure\IInfrastructure;

interface IEmailServer extends IInfrastructure
{
    /**
     * @param list<array<string, mixed>> $attachments
     */
    public function send(string $to, string $title, string $message, array $attachments): void;
}
