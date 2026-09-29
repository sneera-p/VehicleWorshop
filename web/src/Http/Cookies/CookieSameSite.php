<?php

declare(strict_types=1);

namespace Vwork\Web\Http\Cookies;

/**
 * Controls whether the browser attaches a cookie to cross-site requests —
 * the baseline defence against CSRF.
 *
 * @author Senira <senirahan@gmail.com>
 */
enum CookieSameSite: string
{
    /** Never sent cross-site. Users arriving from an external link land logged out. */
    case Strict = 'Strict';

    /** Sent on link navigation, withheld from cross-site posts and subrequests. Browser default. */
    case Lax = 'Lax';

    /** Sent everywhere — no protection. Requires Secure, so never works over plain HTTP. */
    case None = 'None';
}
