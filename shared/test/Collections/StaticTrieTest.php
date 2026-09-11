<?php

declare(strict_types=1);

namespace Vwork\Shared\Test\Collections;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Shared\Collections\StaticTrie;
use Vwork\Shared\Exception\VworkError;

final class StaticTrieTest extends TestCase
{
    #[Test]
    public function search_after_build(): void
    {
        $trie = new StaticTrie();
        $trie->insert('/staff/jobs', 'jobs-handler');
        $trie->build();

        $this->assertSame(['jobs-handler'], $trie->search('/staff/jobs'));
    }

    #[Test]
    public function search_returns_empty_list_for_unknown_key(): void
    {
        $trie = new StaticTrie();
        $trie->insert('/staff/jobs', 'jobs-handler');
        $trie->build();

        $this->assertSame([], $trie->search('/staff/billing'));
    }

    #[Test]
    #[TestWith(['staff/jobs'])]        // no leading separator
    #[TestWith(['staff/jobs/'])]       // trailing separator
    #[TestWith(['/staff//jobs'])]      // doubled separator mid-path
    public function separator_variants_resolve_to_the_same_key(string $searchKey): void
    {
        $trie = new StaticTrie();
        $trie->insert('/staff/jobs', 'jobs-handler');
        $trie->build();

        $this->assertSame(['jobs-handler'], $trie->search($searchKey));
    }

    #[Test]
    public function search_distinguishes_prefix_path_from_full_path(): void
    {
        $trie = new StaticTrie();
        $trie->insert('/staff/jobs/complete', 'complete-handler');
        $trie->build();

        // '/staff/jobs' is a path prefix of an inserted key but was never itself inserted
        $this->assertSame([], $trie->search('/staff/jobs'));
        $this->assertSame(['complete-handler'], $trie->search('/staff/jobs/complete'));
    }

    #[Test]
    public function shorter_and_longer_paths_sharing_a_prefix_do_not_collide(): void
    {
        $trie = new StaticTrie();
        $trie->insert('/staff/jobs', 'list-handler');
        $trie->insert('/staff/jobs/complete', 'complete-handler');
        $trie->build();

        $this->assertSame(['list-handler'], $trie->search('/staff/jobs'));
        $this->assertSame(['complete-handler'], $trie->search('/staff/jobs/complete'));
    }

    #[Test]
    public function insert_without_isDuplicate_allows_unlimited_values_at_same_path(): void
    {
        $trie = new StaticTrie();
        $trie->insert('/staff/jobs', 'first');
        $trie->insert('/staff/jobs', 'second');
        $trie->build();

        $this->assertSame(['first', 'second'], $trie->search('/staff/jobs'));
    }

    #[Test]
    public function insert_with_isDuplicate_throws_when_predicate_matches_an_existing_value(): void
    {
        $trie = new StaticTrie(isDuplicate: fn (string $a, string $b) => $a === $b);
        $trie->insert('/staff/jobs', 'POST');

        $this->expectException(VworkError::class);
        $trie->insert('/staff/jobs', 'POST');
    }

    #[Test]
    public function insert_with_isDuplicate_allows_non_matching_values_at_same_path(): void
    {
        $trie = new StaticTrie(isDuplicate: fn (string $a, string $b) => $a === $b);
        $trie->insert('/staff/jobs', 'GET');
        $trie->insert('/staff/jobs', 'POST');
        $trie->build();

        $this->assertSame(['GET', 'POST'], $trie->search('/staff/jobs'));
    }

    #[Test]
    public function isDuplicate_is_only_checked_against_values_at_the_same_path(): void
    {
        $trie = new StaticTrie(isDuplicate: fn (string $a, string $b) => $a === $b);
        $trie->insert('/staff/jobs', 'POST');
        // same value, different path — should not throw
        $trie->insert('/staff/billing', 'POST');
        $trie->build();

        $this->assertSame(['POST'], $trie->search('/staff/jobs'));
        $this->assertSame(['POST'], $trie->search('/staff/billing'));
    }

    #[Test]
    public function insert_throws_after_build(): void
    {
        $trie = new StaticTrie();
        $trie->build();

        $this->expectException(VworkError::class);
        $trie->insert('/staff/jobs', 'jobs-handler');
    }

    #[Test]
    public function build_throws_when_called_twice(): void
    {
        $trie = new StaticTrie();
        $trie->build();

        $this->expectException(VworkError::class);
        $trie->build();
    }

    #[Test]
    public function search_throws_before_build(): void
    {
        $trie = new StaticTrie();
        $trie->insert('/staff/jobs', 'jobs-handler');

        $this->expectException(VworkError::class);
        $trie->search('/staff/jobs');
    }

    /**
     * @param non-empty-string $separator
     */
    #[Test]
    #[TestWith(['.'])]
    #[TestWith(['-'])]
    #[TestWith([':'])]
    public function custom_separator_is_respected(string $separator): void
    {
        $key = "staff{$separator}jobs";

        $trie = new StaticTrie(separator: $separator);
        $trie->insert($key, 'handler');
        $trie->build();

        $this->assertSame(['handler'], $trie->search($key));
        // the default '/' separator should NOT split this key when a custom one is set
        $this->assertSame([], $trie->search('staff/jobs'));
    }

    #[Test]
    public function values_can_legitimately_include_null(): void
    {
        $trie = new StaticTrie();
        $trie->insert('/staff/jobs', null);
        $trie->build();

        $this->assertSame([null], $trie->search('/staff/jobs'));
        $this->assertSame([], $trie->search('/staff/billing'));
    }
}
