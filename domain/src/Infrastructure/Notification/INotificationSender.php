<?php

declare(strict_types=1);

namespace Vwork\Domain\Infrastructure\Notification;

use Vwork\Domain\Infrastructure\IInfrastructure;

interface INotificationSender extends IInfrastructure
{
    public function send(string $to, string $title, string $message): void;
}
