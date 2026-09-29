<?php

declare(strict_types=1);

namespace Vwork\Shared\Test\Collections;

use Closure;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Shared\Collections\StaticTrie;
use Vwork\Shared\Exception\VworkError;

final class StaticTrieTest extends TestCase
{
    /**
     * A built trie holding each [key, value] pair.
     *
     * @param list<array{string, mixed}> $entries
     * @param (Closure(string): ?string)|null $wildcard
     * @return StaticTrie<mixed>
     */
    private static function trie(array $entries, ?Closure $wildcard = null): StaticTrie
    {
        $trie = new StaticTrie(wildcard: $wildcard);
        foreach ($entries as [$key, $value]) {
            $trie->insert($key, $value);
        }
        $trie->build();

        return $trie;
    }

    // --- fixed paths ---

    #[Test]
    public function finds_values_by_exact_path(): void
    {
        $trie = self::trie([['/staff/jobs', 'list'], ['/staff/jobs/complete', 'complete']]);

        $this->assertSame(['values' => ['list'], 'params' => []], $trie->search('/staff/jobs'));
        $this->assertSame(['complete'], $trie->search('/staff/jobs/complete')['values']);
    }

    #[Test]
    #[TestWith(['/staff/billing'])]  // never inserted
    #[TestWith(['/staff'])]          // only a prefix of an inserted path
    #[TestWith(['/staff/jobs/x'])]   // longer than any inserted path
    public function returns_nothing_for_an_unknown_path(string $key): void
    {
        $trie = self::trie([['/staff/jobs', 'list']]);

        $this->assertSame(['values' => [], 'params' => []], $trie->search($key));
    }

    #[Test]
    #[TestWith(['staff/jobs'])]    // no leading separator
    #[TestWith(['staff/jobs/'])]   // trailing separator
    #[TestWith(['/staff//jobs'])]  // doubled separator
    public function ignores_empty_segments(string $key): void
    {
        $this->assertSame(['list'], self::trie([['/staff/jobs', 'list']])->search($key)['values']);
    }

    #[Test]
    public function keeps_every_value_at_one_path_including_null(): void
    {
        $trie = self::trie([['/jobs', 'first'], ['/jobs', null]]);

        $this->assertSame(['first', null], $trie->search('/jobs')['values']);
    }

    // --- wildcards ---

    /**
     * The test's own wildcard rule: ":name" is a wildcard called "name".
     * The trie doesn't care what the rule is, only what it returns.
     *
     * @return Closure(string): ?string
     */
    private static function colon(): Closure
    {
        return static fn (string $segment): ?string => str_starts_with($segment, ':') ? substr($segment, 1) : null;
    }

    #[Test]
    public function without_a_wildcard_rule_every_segment_is_fixed(): void
    {
        $trie = self::trie([['/jobs/:id', 'show']]);

        $this->assertSame(['show'], $trie->search('/jobs/:id')['values']);
        $this->assertSame([], $trie->search('/jobs/42')['values']);
    }

    #[Test]
    public function a_wildcard_matches_any_segment_and_captures_it(): void
    {
        $trie = self::trie([['/staff/:staff/jobs/:job', 'job']], self::colon());

        $this->assertSame(
            ['values' => ['job'], 'params' => ['staff' => '7', 'job' => '42']],
            $trie->search('/staff/7/jobs/42'),
        );
    }

    #[Test]
    public function a_fixed_segment_beats_a_wildcard(): void
    {
        $trie = self::trie([['/jobs/:id', 'show'], ['/jobs/new', 'new']], self::colon());

        $this->assertSame(['values' => ['new'], 'params' => []], $trie->search('/jobs/new'));
        $this->assertSame(['values' => ['show'], 'params' => ['id' => '42']], $trie->search('/jobs/42'));
    }

    #[Test]
    public function falls_back_to_a_wildcard_when_the_fixed_branch_leads_nowhere(): void
    {
        $trie = self::trie([['/jobs/new', 'new'], ['/jobs/:id/edit', 'edit']], self::colon());

        $this->assertSame(['values' => ['edit'], 'params' => ['id' => 'new']], $trie->search('/jobs/new/edit'));
    }

    #[Test]
    public function a_request_that_looks_like_a_wildcard_is_just_a_value(): void
    {
        $trie = self::trie([['/jobs/:id', 'show']], self::colon());

        $this->assertSame(['id' => ':id'], $trie->search('/jobs/:id')['params']);
    }

    // --- duplicates ---

    #[Test]
    public function is_duplicate_rejects_a_matching_value_at_the_same_path_only(): void
    {
        $trie = new StaticTrie(isDuplicate: fn (string $a, string $b) => $a === $b);
        $trie->insert('/jobs', 'GET');
        $trie->insert('/jobs', 'POST');    // different value: fine
        $trie->insert('/billing', 'GET');  // same value, other path: fine

        $this->expectException(VworkError::class);
        $trie->insert('/jobs', 'GET');
    }

    // --- build lifecycle ---

    #[Test]
    public function search_throws_before_build(): void
    {
        $this->expectException(VworkError::class);
        new StaticTrie()->search('/jobs');
    }

    #[Test]
    public function insert_throws_after_build(): void
    {
        $trie = new StaticTrie();
        $trie->build();

        $this->expectException(VworkError::class);
        $trie->insert('/jobs', 'list');
    }

    #[Test]
    public function build_throws_when_called_twice(): void
    {
        $trie = new StaticTrie();
        $trie->build();

        $this->expectException(VworkError::class);
        $trie->build();
    }

    // --- separator ---

    #[Test]
    public function uses_the_given_separator(): void
    {
        $trie = new StaticTrie(separator: '.');
        $trie->insert('staff.jobs', 'list');
        $trie->build();

        $this->assertSame(['list'], $trie->search('staff.jobs')['values']);
        $this->assertSame([], $trie->search('staff/jobs')['values']);
    }
}
