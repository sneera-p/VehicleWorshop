<?php

declare(strict_types=1);

namespace Vwork\Web\Http;

/**
 * Contains only the Http Response Statuses used by web/
 *
 * @author Senira <senirahan@gmail.com>
 */
enum HttpStatus: int
{
    case Ok = 200;
    case Created = 201;
    case NoContent = 204;

    case Found = 302;

    case BadRequest = 400;
    case Unauthorized = 401;
    case Forbidden = 403;
    case NotFound = 404;
    case MethodNotAllowed = 405;
    case Conflict = 409;
    case UnprocessableEntity = 422;

    case InternalServerError = 500;
    case ServiceUnavailable = 503;

    public function description(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::Created => 'Created',
            self::NoContent => 'No Content',
            self::Found => 'Found',
            self::BadRequest => 'Bad Request',
            self::Unauthorized => 'Unauthorized',
            self::Forbidden => 'Forbidden',
            self::NotFound => 'Not Found',
            self::MethodNotAllowed => 'Method Not Allowed',
            self::Conflict => 'Conflict',
            self::UnprocessableEntity => 'Unprocessable Entity',
            self::InternalServerError => 'Internal Server Error',
            self::ServiceUnavailable => 'Service Unavailable',
        };
    }
}
