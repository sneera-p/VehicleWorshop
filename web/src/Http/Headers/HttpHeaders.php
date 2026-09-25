<?php

declare(strict_types=1);

namespace Vwork\Web\Http\Headers;

/**
 * HTTP headers the app reads or writes.
 * Values are the canonical wire names.
 *
 * @author Senira <senirahan@gmail.com>
 */
enum HttpHeaders: string
{
    // Request
    case Accept = 'Accept';
    case Authorization = 'Authorization';
    case Cookie = 'Cookie';
    case UserAgent = 'User-Agent';

    // Response
    case Allow = 'Allow';
    case CacheControl = 'Cache-Control';
    case ContentDisposition = 'Content-Disposition';
    case Location = 'Location';
    case SetCookie = 'Set-Cookie';
    case XAccelBuffering = 'X-Accel-Buffering';

    // Both
    case ContentLength = 'Content-Length';
    case ContentType = 'Content-Type';

    /**
     * Whether values are a comma-separated list that is safe to split.
     * Everything else is one opaque value: User-Agent and dates contain
     * commas, Cookie uses `;`, and Set-Cookie must never be joined or split.
     */
    public function isList(): bool
    {
        return match ($this) {
            self::Accept,
            self::Allow,
            self::CacheControl => true,
            default => false,
        };
    }

    public function isRequestHeader(): bool
    {
        return match ($this) {
            self::Accept,
            self::Authorization,
            self::Cookie,
            self::UserAgent,
            self::ContentLength,
            self::ContentType => true,
            default => false
        };
    }

    public function isResponseHeader(): bool
    {
        return match ($this) {
            self::Allow,
            self::CacheControl,
            self::ContentDisposition,
            self::Location,
            self::SetCookie,
            self::XAccelBuffering,
            self::ContentLength,
            self::ContentType => true,
            default => false
        };
    }

    /**
     * "Content-Type" => "HTTP_CONTENT_TYPE", built once per worker
     * instead of re-deriving it for every header of every request.
     *
     * @return array<string, self>
     */
    public static function serverKeyMap(): array
    {
        /**
         * $_SERVER key ("HTTP_CONTENT_TYPE") => HttpHeaders case, built once
         * per worker process rather than re-deriving the name transformation
         * on every header of every request.
         *
         * @var array<string, self> | null
         */
        static $map = null;

        if ($map === null) {
            $map = [];
            foreach (self::cases() as $header) {
                if (!$header->isRequestHeader()) {
                    continue;
                }
                $key = strtoupper(str_replace('-', '_', $header->value));
                // CGI puts these two in $_SERVER without the HTTP_ prefix
                if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                    $map[$key] = $header;
                } else {
                    $map["HTTP_{$key}"] = $header;
                }
            }
        }

        return $map;
    }
}
