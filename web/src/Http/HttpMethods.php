<?php

declare(strict_types=1);

namespace Vwork\Web\Http;

/**
 * Contains only the Http Methods used by web/
 *
 * @author Senira <senirahan@gmail.com>
 */
enum HttpMethods: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case HEAD = 'HEAD';
    case OPTIONS = 'OPTIONS';
}
