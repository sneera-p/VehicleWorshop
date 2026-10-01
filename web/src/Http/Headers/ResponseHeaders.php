<?php

declare(strict_types=1);

namespace Vwork\Web\Http\Headers;

/**
 * Headers the app may set on responses.
 * Values are the canonical wire names.
 *
 * Set-Cookie is deliberately absent: cookies go through
 * Response::addCookie(), never through the header list.
 *
 * @author Senira <senirahan@gmail.com>
 */
enum ResponseHeaders: string implements HttpHeader
{
    use HeaderLine;

    case Allow = 'Allow';
    case CacheControl = 'Cache-Control';
    case ContentDisposition = 'Content-Disposition';
    case ContentLength = 'Content-Length';
    case ContentType = 'Content-Type';
    case Location = 'Location';
    case Vary = 'Vary';
    case XAccelBuffering = 'X-Accel-Buffering';

    /**
     * List headers are appended to; everything else is replaced.
     */
    public function isList(): bool
    {
        return match ($this) {
            self::Allow,
            self::CacheControl,
            self::Vary => true,
            self::ContentDisposition,
            self::ContentLength,
            self::ContentType,
            self::Location,
            self::XAccelBuffering => false,
        };
    }
}
