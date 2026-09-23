<?php

declare(strict_types=1);

namespace Vwork\Shared\Test\Stubs;

use Vwork\Shared\Collections\Registry;

/**
 * Minimal concrete subclass existing only to exercise Registry's own
 * caching/validation mechanics in isolation, independent of any real
 * production registry (DomainRegistry, AppServiceRegistry, ...).
 *
 * @extends Registry<StubRegistry>
 */
final class StubRegistry extends Registry
{
    protected object $registrar {
        get => $this;
    }

    /**
     * Exposes the otherwise-protected resolve() so tests can call it directly.
     *
     * @param class-string $category
     * @param class-string $key
     */
    public function resolvePublic(string $category, string $key): object
    {
        return $this->resolve($category, $key, $this);
    }
}
