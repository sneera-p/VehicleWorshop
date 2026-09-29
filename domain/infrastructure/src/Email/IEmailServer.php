<?php

declare(strict_types=1);

namespace Vwork\Domain\Infrastructure\Email;

use Vwork\Domain\Infrastructure\IInfrastructure;

/**
 * Common interface for email (SMTP) operations
 * @author Senira <senirahan@gmail.com>
 */
interface IEmailServer extends IInfrastructure
{
    /**
     * @param list<array<string, mixed>> $attachments
     */
    public function send(string $to, string $title, string $message, array $attachments): void;
}
