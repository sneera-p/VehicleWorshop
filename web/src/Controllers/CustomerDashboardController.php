<?php

declare(strict_types=1);

namespace Vwork\Web\Controllers;

use Vwork\Shared\Collections\IRegistry;
use Vwork\Web\Http\Request;
use Vwork\Web\Http\Response;

/**
 * Handles Customer Dashboard flows.
 *
 * @phpstan-import-type StringMap from IController
 */
final readonly class CustomerDashboardController extends ControllerBase
{
    public function __construct(IRegistry $registry)
    {
        parent::__construct($registry);
    }

    /**
     * Renders Customer Overview Dashboard (GET /customer/overview or GET /customer/dashboard)
     *
     * @param StringMap $attr
     */
    #[ControllerAction]
    public function overviewPage(Request $req, array $attr): Response
    {
        return $this->view($req, 'customer/dashboard', [
            'title'        => 'Overview - AutoCare Customer Portal',
            'active_route' => 'overview',
            'user'         => null,
            'stats'        => [],
            'current_service' => null,
            'recent_activity' => [],
        ], 'layouts/customer');
    }
}
