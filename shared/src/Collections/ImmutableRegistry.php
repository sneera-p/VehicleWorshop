<?php

declare(strict_types=1);

namespace Vwork\Shared\Collections;

use Closure;
use Override;
use Vwork\Shared\Exception\VworkError;

/**
 * A lazy-build-and-cache registry whose bindings are fixed at construction.
 *
 * Each binding is a closure that builds one service. Nothing runs until the
 * first get() for it; after that, every caller gets the same instance.
 * Bindings can't be added or replaced afterwards, so what a process can
 * resolve is decided once, at boot.
 *
 * Every binding must be a subtype of one of $allowed, checked up front so a
 * stray binding fails at boot instead of on first use.
 *
 * Not readonly on purpose: $resolved fills up as services are first used.
 * That state is the same for every request, so it's safe in worker mode.
 *
 * Meant to be owned, not extended.
 *
 * @author Senira <senirahan@gmail.com>
 */
final class ImmutableRegistry implements IRegistry
{
    /** @var array<class-string, object> */
    private array $resolved = [];

    /** @var array<class-string, bool> */
    private array $building = [];

    /**
     * @param array<class-string, Closure(self): object> $bindings
     * @param list<class-string> $allowed
     *
     * @throws VworkError for the rejected binding.
     */
    public function __construct(
        private readonly array $bindings,
        private readonly array $allowed,
    ) {
        foreach (array_keys($bindings) as $name) {
            if (!array_any($this->allowed, static fn (string $base) => is_a($name, $base, allow_string: true))) {
                throw new VworkError("{$name} not allowed");
            }
        }
    }

    #[Override]
    public function get(string $name): object
    {
        $cached = $this->resolved[$name] ?? null;
        if ($cached instanceof $name) {
            return $cached;
        }

        $factory = $this->bindings[$name]
            ?? throw new VworkError("No binding registered for {$name}");

        if (isset($this->building[$name])) {
            throw new VworkError('Circular binding: ' . implode(' -> ', [...array_keys($this->building), $name]));
        }

        $this->building[$name] = true;
        try {
            $instance = $factory($this);
        } finally {
            unset($this->building[$name]);
        }

        if (!$instance instanceof $name) {
            throw new VworkError("Binding for {$name} built a " . $instance::class);
        }

        return $this->resolved[$name] = $instance;
    }
}
