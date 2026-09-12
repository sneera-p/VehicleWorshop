<?php

declare(strict_types=1);

namespace Vwork\Web\Http;

enum HttpHeaders: string
{
    case ContentType = 'Content-Type';
    case Authorization = 'Authorization';
    case Location = 'Location';
    case Allow = 'Allow';
    case Accept = 'Accept';
    case CacheControl = 'Cache-Control';
    case ContentDisposition = 'Content-Disposition';
    case ContentLength = 'Content-Length';
    case XAccelBuffering = 'X-Accel-Buffering';
    case Cookie = 'Cookie';       // needed for Request parsing — missing from your diagram's list
    case SetCookie = 'Set-Cookie'; // needed for Response cookies — same
    case UserAgent = 'User-Agent';
}
