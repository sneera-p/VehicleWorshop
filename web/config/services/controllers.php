<?php

declare(strict_types=1);

use Vwork\Shared\Collections\IRegistry;
use Vwork\Web\Controllers\DummyController;
use Vwork\Web\Controllers\CustomerAuthController;
use Vwork\Web\Controllers\CustomerDashboardController;
use Vwork\Web\Controllers\IController;

/**
 * @var array<
 *  class-string<IController>,
 *  Closure(IRegistry): object
 * >
 */
return [
    DummyController::class             => static fn (IRegistry $r): DummyController => new DummyController($r),
    CustomerAuthController::class      => static fn (IRegistry $r): CustomerAuthController => new CustomerAuthController($r),
    CustomerDashboardController::class => static fn (IRegistry $r): CustomerDashboardController => new CustomerDashboardController($r),
];
