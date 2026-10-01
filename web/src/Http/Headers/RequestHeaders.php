<?php

declare(strict_types=1);

namespace Vwork\Web\Http\Headers;

/**
 * Headers the app reads from incoming requests.
 * Values are the canonical wire names.
 *
 * @author Senira <senirahan@gmail.com>
 */
enum RequestHeaders: string implements HttpHeader
{
    use HeaderLine;

    case Accept = 'Accept';
    case Authorization = 'Authorization';
    case ContentLength = 'Content-Length';
    case ContentType = 'Content-Type';
    case Cookie = 'Cookie';
    case Host = 'Host';
    case Origin = 'Origin';
    case UserAgent = 'User-Agent';
    case XCsrfToken = 'X-CSRF-Token';

    /**
     * Only Accept is split. User-Agent contains commas, Cookie uses `;`,
     * and the rest are single values.
     */
    public function isList(): bool
    {
        return match ($this) {
            self::Accept => true,
            self::Authorization,
            self::ContentLength,
            self::ContentType,
            self::Cookie,
            self::Host,
            self::Origin,
            self::UserAgent,
            self::XCsrfToken => false,
        };
    }

    /**
     * $_SERVER key => header, e.g. "HTTP_USER_AGENT" => UserAgent.
     * Built once per worker, not on every request.
     *
     * @return array<string, self>
     */
    public static function serverKeyMap(): array
    {
        /** @var array<string, self>|null $map */
        static $map = null;

        if ($map === null) {
            $map = [];
            foreach (self::cases() as $header) {
                $key = strtoupper(str_replace('-', '_', $header->value));
                // CGI puts these two in $_SERVER without the HTTP_ prefix
                $map[in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? $key : "HTTP_{$key}"] = $header;
            }
        }

        return $map;
    }
}
