<?php

declare(strict_types=1);

namespace Vwork\Web\Pipeline;

use Vwork\Web\WebError;

/**
 * Settings for one route, shared by all of its middleware.
 * For example: which roles may use this route.
 *
 * @author Senira <senirahan@gmail.com>
 */
final readonly class PipelineContext
{
    public function __construct(
        public string $roles,
    ) {
    }

    /**
     * @param array<string, mixed> $ctx the route's "context" config
     * @throws WebError if a setting is missing or has the wrong type
     */
    public static function fromArray(array $ctx): self
    {
        // Route config is written by hand, so check it here, at boot.
        $roles = $ctx['roles'] ?? null;
        if (!is_string($roles)) {
            throw new WebError('Route context needs "roles" as a string');
        }

        return new self(roles: $roles);
    }
}
