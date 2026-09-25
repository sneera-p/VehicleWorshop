<?php

declare(strict_types=1);

namespace Vwork\Web\Registry;

use Vwork\Domain\IDomainRegistry;

/**
 * The one door into domain/, controllers and middleware.
 *
 * @author Senira <senirahan@gmail.com>
 */
interface IAppServiceRegistry extends IDomainRegistry, IHttpRegistry
{
}
