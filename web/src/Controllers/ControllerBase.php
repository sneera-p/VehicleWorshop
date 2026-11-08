<?php

declare(strict_types=1);

namespace Vwork\Web\Controllers;

use Vwork\Shared\Collections\IRegistry;
use Vwork\Web\Http\Cookies\HttpCookies;
use Vwork\Web\Http\Headers\RequestHeaders;
use Vwork\Web\Http\Request\Request;
use Vwork\Web\Utils\AssetParser;
use Vwork\Web\Utils\CsrfToken;
use Vwork\Web\Utils\View;

/**
 * The parent of every controller.
 *
 * A controller reads the request, asks a facade for data, and then picks
 * one of the helpers below to turn that data into a Response.
 *
 * @author Senira <senirahan@gmail.com>
 */
abstract readonly class ControllerBase implements IController
{
    protected View $view;
    private CsrfToken $csrf;
    private AssetParser $assets;

    public function __construct(IRegistry $registry)
    {
        $this->view = $registry->get(view::class);
        $this->csrf = $registry->get(CsrfToken::class);
        $this->assets = $registry->get(AssetParser::class);
    }

    /**
     * Renders $template into an HTML response, optionally wrapped in $layout.
     *
     * The template receives all of $data as local variables, plus 'csrf':
     * a token for this session, for the form's hidden _csrf field.
     *
     * The layout receives only three:
     *  - 'content' (the rendered template)
     *  - 'title' (defaults to 'No Title')
     *  - 'csrf' (for the <meta> tag that fetch() reads)
     *  - 'assets' (assets parser)
     *
     * @param string $template - view to render, relative to the views folder
     * @param array<string, mixed> $data - the template's local variables.
     *     ('title', if present, is also passed to the layout)
     * @param ?string $layout - wrapping view; must echo $content and $title.
     *     (null sends the template on its own)
     */
    protected function view(Request $request, string $template, array $data = [], ?string $layout = null): string
    {
        // no session yet (login page) still gets a token; SameOriginMiddleware guards that case
        $csrf = $request->headers[RequestHeaders::XCsrfToken][0]
            ?? $this->csrf->create($request->cookies[HttpCookies::SessionToken] ?? '');

        $html = $this->view->render($template, [
            ...$data,
            'csrf' => $csrf,
            'assets' => $this->assets->urls,
        ]);

        if ($layout !== null) {
            $html = $this->view->render($layout, [
                'content' => $html,
                'title' => $data['title'] ?? 'No Title',
                'csrf' => $csrf,
                'content_name' => $template,
                'active_route' => $data['active_route'] ?? 'overview',
                'assets' => $this->assets->urls,
            ]);
        }

        return $html;
    }

    // /**
    //  * Picks the client's most preferred supported type: highest q wins,
    //  * a specific type beats a wildcard at the same q. Empty Accept means JSON.
    //  *
    //  * @param list<string> $accept
    //  * @return 'application/json'|'application/cbor'|null
    //  */
    // private static function negotiatePayloadType(array $accept): ?string
    // {
    //     if ($accept === []) {
    //         return 'application/json';
    //     }

    //     $best = null;
    //     $bestQ = 0.0;
    //     $bestIsWildcard = true;

    //     foreach ($accept as $range) {
    //         $params = array_map('trim', explode(';', $range));

    //         // parse q value
    //         $q = 1.0;
    //         foreach ($params as $param) {
    //             if (str_starts_with($param, 'q=')) {
    //                 $q = (float) substr($param, 2);
    //             }
    //         }

    //         // parse Content-Type
    //         $type = strtolower(array_shift($params));
    //         [$match, $isWildcard] = match ($type) {
    //             'application/json', 'application/cbor' => [$type, false],
    //             '*/*', 'application/*' => ['application/json', true],
    //             default => [null, true],
    //         };

    //         if ($match === null || $q <= 0.0) {
    //             continue;
    //         }

    //         if ($q > $bestQ || ($q === $bestQ && $bestIsWildcard && !$isWildcard)) {
    //             $best = $match;
    //             $bestQ = $q;
    //             $bestIsWildcard = $isWildcard;
    //         }
    //     }

    //     return $best;
    // }
}
