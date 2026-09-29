<?php

declare(strict_types=1);

namespace Vwork\Domain;

use Vwork\Shared\Exception\VworkError;
use Vwork\Domain\Infrastructure\IInfrastructure;
use Vwork\Domain\Modules\IFacade;

/**
 * The one door into domain/ — hands out facades and infrastructure by interface,
 * building each lazily on first request and reusing the same instance after.
 *
 * @author Senira <senirahan@gmail.com>
 */
interface IDomainRegistry
{
    /**
     * @param class-string<IInfrastructure> $name
     * @throws VworkError - if there is no registered infrastructure
     *
     * ```php
     *      $registry->getInfrastructure(ICache::class)
     * ```
     */
    public function getInfrastructure(string $name): IInfrastructure;

    /**
     * @param class-string<IFacade> $name
     * @throws VworkError - if there is no registered facade
     *
     * ```php
     *      $registry->getFacade(IJobFacade::class)
     * ```
     */
    public function getFacade(string $name): IFacade;
}
