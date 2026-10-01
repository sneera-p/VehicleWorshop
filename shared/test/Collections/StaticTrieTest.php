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
     * A built trie holding each [key, slot, value].
     * Wildcards use the test's own rule: ":name" is a wildcard called "name".
     *
     * @param list<array{string, string, mixed}> $entries
     * @param non-empty-string $separator
     * @return StaticTrie<string, mixed>
     */
    private static function trie(array $entries, bool $wildcards = true, string $separator = '/'): StaticTrie
    {
        $rule = static fn (string $segment): ?string => str_starts_with($segment, ':') ? substr($segment, 1) : null;

        $trie = new StaticTrie($separator, $wildcards ? $rule : null);
        foreach ($entries as [$key, $slot, $value]) {
            $trie->insert($key, $slot, $value);
        }

        return $trie;
    }

    private const array ROUTES = [
        ['/jobs', 'GET', 'list'],
        ['/jobs', 'POST', 'create'],
        ['/jobs/new', 'GET', 'new'],
        ['/jobs/:id', 'GET', 'show'],
        ['/jobs/:id/notes', 'POST', 'note'],
        ['/staff/:staff/jobs/:job', 'GET', 'staff-job'],
        ['/', 'GET', 'home'],
    ];

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $params
     */
    #[Test]
    #[TestWith(['/jobs', ['GET' => 'list', 'POST' => 'create'], []])]           // every slot at one path
    #[TestWith(['/jobs/new', ['GET' => 'new'], []])]                             // fixed beats wildcard
    #[TestWith(['/jobs/42', ['GET' => 'show'], ['id' => '42']])]
    #[TestWith(['/jobs/42/notes', ['POST' => 'note'], ['id' => '42']])]
    #[TestWith(['/staff/7/jobs/42', ['GET' => 'staff-job'], ['staff' => '7', 'job' => '42']])]
    #[TestWith(['/jobs/:id', ['GET' => 'show'], ['id' => ':id']])]               // looks like a wildcard, is just a value
    #[TestWith(['/', ['GET' => 'home'], []])]
    #[TestWith(['', ['GET' => 'home'], []])]
    #[TestWith(['jobs/', ['GET' => 'list', 'POST' => 'create'], []])]           // empty segments ignored
    #[TestWith(['//jobs//new', ['GET' => 'new'], []])]
    public function search_finds_values_and_params(string $key, array $values, array $params): void
    {
        $this->assertSame([$values, $params], self::trie(self::ROUTES)->search($key));
    }

    #[Test]
    #[TestWith(['/billing'])]          // never inserted
    #[TestWith(['/staff'])]            // a node on the way, not a value
    #[TestWith(['/jobs/42/x'])]        // longer than any route
    #[TestWith(['/jobs/new/notes'])]   // fixed "new" wins its position for good: no fallback to :id
    public function search_returns_nothing_for_an_unknown_path(string $key): void
    {
        $this->assertSame([[], []], self::trie(self::ROUTES)->search($key));
    }

    #[Test]
    public function without_a_wildcard_rule_every_segment_is_fixed(): void
    {
        $trie = self::trie([['/jobs/:id', 'GET', 'show']], wildcards: false);

        $this->assertSame([['GET' => 'show'], []], $trie->search('/jobs/:id'));
        $this->assertSame([[], []], $trie->search('/jobs/42'));
    }

    #[Test]
    public function uses_the_given_separator(): void
    {
        $trie = self::trie([['staff.jobs', 'x', 'list']], separator: '.');

        $this->assertSame([['x' => 'list'], []], $trie->search('staff.jobs'));
        $this->assertSame([[], []], $trie->search('staff/jobs'));
    }

    #[Test]
    public function a_null_value_still_fills_its_slot(): void
    {
        $this->assertSame([['GET' => null], []], self::trie([['/jobs', 'GET', null]])->search('/jobs'));
    }

    /**
     * @param list<array{string, string, mixed}> $entries
     */
    #[Test]
    #[TestWith([[['/jobs', 'GET', 1], ['/jobs', 'GET', 2]]])]          // same slot, same path
    #[TestWith([[['/jobs', 'GET', 1], ['/jobs/', 'GET', 2]]])]         // same path once empty segments go
    #[TestWith([[['/jobs', 'GET', null], ['/jobs', 'GET', 2]]])]       // null still occupies the slot
    #[TestWith([[['/jobs/:id', 'GET', 1], ['/jobs/:jobId/x', 'GET', 2]]])] // two wildcard names at one position
    public function insert_rejects_a_conflict(array $entries): void
    {
        $this->expectException(VworkError::class);
        self::trie($entries);
    }

    #[Test]
    public function insert_allows_the_same_slot_on_another_path_and_another_slot_on_the_same_path(): void
    {
        $trie = self::trie([['/jobs', 'GET', 1], ['/jobs', 'POST', 2], ['/billing', 'GET', 3]]);

        $this->assertSame([['GET' => 1, 'POST' => 2], []], $trie->search('/jobs'));
    }
}
