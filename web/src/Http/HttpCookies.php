<?php

declare(strict_types=1);

namespace Vwork\Web\Http;

enum HttpCookies: string
{
    case SessionToken = 'session_token';
    case RefreshToken = 'refresh_token';
    case CsrfToken = 'csrf_token';
}
