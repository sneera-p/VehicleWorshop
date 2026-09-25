<?php

declare(strict_types=1);

namespace Vwork\Web\Router;

enum RouteMatchStatus: string
{
    /** There is a pipeline and you can use it */
    case Found = 'Found';

    /** There is no pipeline */
    case NotFound = 'Not Found';

    /** There is a pipelin but your method is not permitted here */
    case NotAllowed = 'Not Allowed';
}
