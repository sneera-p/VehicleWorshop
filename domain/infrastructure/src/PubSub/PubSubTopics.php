<?php

declare(strict_types=1);

namespace Vwork\Domain\Infrastructure\PubSub;

/**
 * All Topics that go through the PubSub are here
 * @author Senira <senirahan@gmail.com>
 */
enum PubSubTopics: string
{
    case JobUpdated = 'job.updated';
    case StaffUpdated = 'staff.updated';
    case AppointmentUpdated = 'appointment.updated';
    case VehicleUpdated = 'vehicle.updated';
    case InventoryUpdated = 'inventory.updated';
}
