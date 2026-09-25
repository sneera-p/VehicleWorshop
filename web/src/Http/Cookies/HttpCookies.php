<?php

declare(strict_types=1);

namespace Vwork\Web\Http\Cookies;

/**
 * Contains only the Cookies used by web/
 *
 * @author Senira <senirahan@gmail.com>
 */
enum HttpCookies: string
{
    case SessionToken = 'session_token';
    case RefreshToken = 'refresh_token';
    case CsrfToken = 'csrf_token';
}
