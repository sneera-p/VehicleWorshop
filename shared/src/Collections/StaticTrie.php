<?php

declare(strict_types=1);

namespace Vwork\Shared\Collections;

use Closure;
use Vwork\Shared\Exception\VworkError;

/**
 * A single node in a StaticTrie
 *
 * @template T - value type held by nodes in this tree
 */
final class TrieNode
{
    /** @var array<string, TrieNode<T>> fixed segments, keyed by the segment itself */
    public array $children = [];

    /** @var array<string, TrieNode<T>> wildcard segments, keyed by wildcard name */
    public array $wildcards = [];

    /** @var list<T> */
    public array $values = [];
}

/**
 * Build-once, read-only trie, keyed by separator-delimited segments
 * (e.g. "/staff/jobs/42" splits into ["staff", "jobs", "42"]).
 *
 * Insert freely until build(), then only search() is allowed — this split
 * exists so a router (or anything else that needs a static lookup tree)
 * can construct its whole structure once at bootstrap and never worry
 * about mutation races once requests start flowing through it.
 *
 * Segments can be wildcards. The trie doesn't know what a wildcard looks
 * like — the owner decides, by passing $wildcard. A wildcard matches any
 * one segment, and search() hands back what it matched, by name.
 *
 * @template T - value type held by this tree (e.g. a pre-built route pipeline)
 */
final class StaticTrie
{
    /** @var TrieNode<T> */
    private TrieNode $root;

    public private(set) bool $isBuilt;

    /**
     * @param non-empty-string $separator
     * @param (Closure(T, T): bool) | null $isDuplicate - Checks duplicate entries in same node. Omit to allow duplicates
     * @param (Closure(string): ?string) | null $wildcard - Given a segment of an inserted key, returns
     *        its wildcard name, or null if it is a fixed segment. Omit for fixed segments only.
     */
    public function __construct(
        private readonly string $separator = '/',
        private readonly ?Closure $isDuplicate = null,
        private readonly ?Closure $wildcard = null,
    ) {
        $this->root = new TrieNode();
        $this->isBuilt = false;
    }

    /**
     * Splits a key into its segments, dropping empty segments.
     *
     * @return list<string>
     */
    private function segments(string $key): array
    {
        return array_values(array_filter(
            explode($this->separator, $key),
            fn (string $segment) => $segment !== '',
        ));
    }

    /**
     * @param T $value
     */
    public function insert(string $key, mixed $value): void
    {
        if ($this->isBuilt) {
            throw new VworkError("Cannot insert after trie is built");
        }

        $node = $this->root;
        foreach ($this->segments($key) as $segment) {
            $name = $this->wildcard === null ? null : ($this->wildcard)($segment);

            // Wildcards live apart from fixed segments, so search() never
            // has to ask the closure again.
            $node = $name === null
                ? $node->children[$segment] ??= new TrieNode()
                : $node->wildcards[$name] ??= new TrieNode();
        }

        if ($this->isDuplicate !== null) {
            foreach ($node->values as $existing) {
                if (($this->isDuplicate)($existing, $value)) {
                    throw new VworkError("Duplicate value inserted at path \"{$key}\"");
                }
            }
        }

        $node->values[] = $value;
    }

    public function build(): void
    {
        if ($this->isBuilt) {
            throw new VworkError("Trie already built you Donkey");
        }

        $this->isBuilt = true;
    }

    /**
     * Finds the values stored under $key, and what each wildcard matched.
     *
     * @return array{values: list<T>, params: array<string, string>}
     *         values is empty when nothing matches
     */
    public function search(string $key): array
    {
        if (!$this->isBuilt) {
            throw new VworkError("Can't search without building the trie");
        }

        return $this->find($this->root, $this->segments($key), 0, [])
            ?? ['values' => [], 'params' => []];
    }

    /**
     * Walks down one segment at a time. A fixed segment is tried first,
     * so "/jobs/new" beats a wildcard at the same place. If that branch
     * leads nowhere, we step back and try the wildcards instead.
     *
     * @param TrieNode<T> $node
     * @param list<string> $segments
     * @param array<string, string> $params
     * @return array{values: list<T>, params: array<string, string>}|null
     */
    private function find(TrieNode $node, array $segments, int $i, array $params): ?array
    {
        if ($i === count($segments)) {
            return $node->values === [] ? null : ['values' => $node->values, 'params' => $params];
        }

        $segment = $segments[$i];

        if (isset($node->children[$segment])) {
            $found = $this->find($node->children[$segment], $segments, $i + 1, $params);
            if ($found !== null) {
                return $found;
            }
        }

        foreach ($node->wildcards as $name => $child) {
            $found = $this->find($child, $segments, $i + 1, [...$params, $name => $segment]);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
