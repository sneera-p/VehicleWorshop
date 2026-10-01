<?php

declare(strict_types=1);

namespace Vwork\Shared\Collections;

use Closure;
use Vwork\Shared\Exception\VworkError;

/**
 * A single node in a StaticTrie.
 *
 * @template K of array-key - slot type (eg: HTTP method names)
 * @template T - value type
 */
final class TrieNode
{
    /** @var array<string, TrieNode<K, T>> - fixed segments, keyed by the segment itself */
    public array $children = [];

    /** @var TrieNode<K, T>|null - the one wildcard allowed at this position */
    public ?TrieNode $wildcard = null;

    /** @var string|null - what that wildcard is called (eg: "id" for {id}) */
    public ?string $wildcardName = null;

    /** @var array<K, T> - one value per slot */
    public array $values = [];
}

/**
 * Build-once, read-only trie, keyed by separator-delimited segments
 * ("/staff/jobs/42" splits into ["staff", "jobs", "42"]).
 *
 * Insert freely until build(), then only search(). A router fills it once
 * at boot; requests only ever read it.
 *
 * A segment can be a wildcard: the owner decides what one looks like by
 * passing $wildcard. Each position allows one wildcard name, so
 * "/jobs/{id}" and "/jobs/{jobId}/x" is a boot error, not two branches.
 *
 * @template K of array-key - slot type (eg: HTTP method names)
 * @template T - value type (eg: a pre-built pipeline)
 */
final class StaticTrie
{
    /** @var TrieNode<K, T> */
    private TrieNode $root;

    /** @var Closure(string): ?string */
    private readonly Closure $wildcard;

    /**
     * @param non-empty-string $separator (eg: '/' in routers)
     * @param (Closure(string): ?string)|null $wildcard - given a segment, returns its
     *        wildcard name, or null for a fixed segment. Omit for fixed segments only.
     */
    public function __construct(
        private readonly string $separator,
        ?Closure $wildcard = null,
    ) {
        $this->root = new TrieNode();
        $this->wildcard = $wildcard ?? static fn (string $segment): ?string => null;
    }

    /**
     * Splits a key into its segments, dropping empty ones.
     *
     * @return list<string>
     */
    private function segments(string $key): array
    {
        return array_values(array_filter(
            explode($this->separator, $key),
            static fn (string $segment): bool => $segment !== '',
        ));
    }

    /**
     * @param K $slot - which value at this key (eg: the HTTP method)
     * @param T $value
     * @throws VworkError if built, if two wildcard names share a position,
     *     or if $key already has a value in $slot
     */
    public function insert(string $key, int|string $slot, mixed $value): void
    {
        $node = $this->root;
        foreach ($this->segments($key) as $segment) {
            $name = ($this->wildcard)($segment);

            if ($name === null) {
                $node = $node->children[$segment] ??= new TrieNode();
                continue;
            }

            // one name per position: {id} here and {jobId} here is a typo
            if ($node->wildcardName !== null && $node->wildcardName !== $name) {
                throw new VworkError("\"{$key}\" names {{$name}} where another route has {{$node->wildcardName}}");
            }
            $node->wildcardName = $name;
            $node = $node->wildcard ??= new TrieNode();
        }

        if (array_key_exists($slot, $node->values)) {
            throw new VworkError("Duplicate {$slot} at \"{$key}\"");
        }
        $node->values[$slot] = $value;
    }

    /**
     * Finds the values stored under $key, and what each wildcard matched.
     *
     * @return array{array<K, T>, array<string, string>}
     * values is empty when nothing matches
     */
    public function search(string $key): array
    {
        $node = $this->root;
        $params = [];

        foreach ($this->segments($key) as $segment) {
            if (isset($node->children[$segment])) {
                $node = $node->children[$segment];
            } elseif ($node->wildcard !== null && $node->wildcardName !== null) {
                $params[$node->wildcardName] = $segment;
                $node = $node->wildcard;
            } else {
                return [[], []];
            }
        }

        return [$node->values, $params];
    }
}
