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
    /** @var array<string, TrieNode<T>> */
    public array $children = [];

    /** @var list<T> */
    public array $values = [];
}

/**
 * Build-once, read-only trie, keyed by separator-delimited segments
 * (e.g. "/staff/jobs/{id}" splits into ["staff", "jobs", "{id}"]).
 *
 * Insert freely until build(), then only search() is allowed — this split
 * exists so a router (or anything else that needs a static lookup tree)
 * can construct its whole structure once at bootstrap and never worry
 * about mutation races once requests start flowing through it.
 *
 * @template T - value type held by this tree (e.g. a pre-built route pipeline)
 */
final class StaticTrie
{
    /** @var TrieNode<T> */
    private TrieNode $root;

    private bool $isBuilt;

    /**
     * @param non-empty-string $separator
     * @param (Closure(T, T): bool) | null $isDuplicate - Checks duplicate entries in same node. Omit to allow duplicates
     */
    public function __construct(
        private readonly string $separator = '/',
        private readonly ?Closure $isDuplicate = null,
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
            $node = $node->children[$segment] ??= new TrieNode();
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
     * @return list<T>
     */
    public function search(string $key): array
    {
        if (!$this->isBuilt) {
            throw new VworkError("Can't search without building the trie");
        }

        $node = $this->root;
        foreach ($this->segments($key) as $segment) {
            if (!isset($node->children[$segment])) {
                return [];
            }
            $node = $node->children[$segment];
        }

        return $node->values;
    }
}
