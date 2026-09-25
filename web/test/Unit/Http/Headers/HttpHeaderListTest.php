<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Http\Headers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Web\Http\Headers\HttpHeaderList;
use Vwork\Web\Http\Headers\HttpHeaders;
use Vwork\Web\WebError;

final class HttpHeaderListTest extends TestCase
{
    #[Test]
    public function single_value_headers_replace_on_set(): void
    {
        $list = new HttpHeaderList();
        $list[HttpHeaders::ContentType] = 'text/plain';
        $list[HttpHeaders::ContentType] = 'text/html';

        $this->assertSame('text/html', $list[HttpHeaders::ContentType]);
        $this->assertSame(['Content-Type' => ['text/html']], $list->list);
    }

    #[Test]
    public function list_headers_append_on_set(): void
    {
        $list = new HttpHeaderList();
        $list[HttpHeaders::CacheControl] = 'no-store';
        $list[HttpHeaders::CacheControl] = 'private';

        $this->assertSame(['no-store', 'private'], $list[HttpHeaders::CacheControl]);
    }

    #[Test]
    public function missing_headers_read_as_null_and_do_not_exist(): void
    {
        $list = new HttpHeaderList();

        $this->assertNull($list[HttpHeaders::Location]);
        $this->assertFalse(isset($list[HttpHeaders::Location]));
    }

    #[Test]
    public function unset_removes_every_value(): void
    {
        $list = new HttpHeaderList();
        $list[HttpHeaders::CacheControl] = 'no-store';
        $list[HttpHeaders::CacheControl] = 'private';

        $this->assertTrue(isset($list[HttpHeaders::CacheControl]));
        unset($list[HttpHeaders::CacheControl]);

        $this->assertFalse(isset($list[HttpHeaders::CacheControl]));
        $this->assertSame([], $list->list);
    }

    #[Test]
    #[TestWith(["/jobs\r\nSet-Cookie: session_token=evil"])]
    #[TestWith(["/jobs\nX: y"])]
    #[TestWith(["/jobs\r"])]
    #[TestWith(["/jobs\0"])]
    public function rejects_values_that_could_split_the_header(string $value): void
    {
        $list = new HttpHeaderList();

        $this->expectException(WebError::class);
        $list[HttpHeaders::Location] = $value;
    }

    #[Test]
    public function to_lines_emits_one_line_per_value(): void
    {
        $list = new HttpHeaderList();
        $list[HttpHeaders::ContentType] = 'text/html';
        $list[HttpHeaders::CacheControl] = 'no-store';
        $list[HttpHeaders::CacheControl] = 'private';

        $this->assertSame(
            ['Content-Type: text/html', 'Cache-Control: no-store', 'Cache-Control: private'],
            iterator_to_array($list->toLines(), false),
        );
    }

    #[Test]
    public function iterates_with_enum_keys(): void
    {
        $list = new HttpHeaderList();
        $list[HttpHeaders::ContentType] = 'text/html';
        $list[HttpHeaders::Allow] = 'GET';

        $seen = [];
        foreach ($list as $header => $values) {
            $seen[] = [$header, $values];
        }

        $this->assertSame([
            [HttpHeaders::ContentType, ['text/html']],
            [HttpHeaders::Allow, ['GET']],
        ], $seen);
    }

    /**
     * @param array<string, string> $server
     * @param array<string, list<string>> $expected
     */
    #[Test]
    #[TestWith([[], []])]
    // CGI puts these two in $_SERVER without the HTTP_ prefix
    #[TestWith([['CONTENT_TYPE' => 'application/json', 'CONTENT_LENGTH' => '42'], ['Content-Type' => ['application/json'], 'Content-Length' => ['42']]])]
    #[TestWith([['HTTP_CONTENT_TYPE' => 'application/json'], []])]
    // unknown headers, non-header keys, and response-only headers are ignored
    #[TestWith([['HTTP_X_MADE_UP' => 'x', 'SERVER_NAME' => 'localhost', 'HTTP_LOCATION' => '/x'], []])]
    // list headers split on commas, trimmed, empties dropped
    #[TestWith([['HTTP_ACCEPT' => 'text/html, application/json ,,'], ['Accept' => ['text/html', 'application/json']]])]
    // everything else stays one opaque value, commas included
    #[TestWith([['HTTP_USER_AGENT' => 'Mozilla/5.0 (KHTML, like Gecko)'], ['User-Agent' => ['Mozilla/5.0 (KHTML, like Gecko)']]])]
    #[TestWith([['HTTP_COOKIE' => 'a=1, b=2; c=3'], ['Cookie' => ['a=1, b=2; c=3']]])]
    #[TestWith([['HTTP_USER_AGENT' => '   '], []])]
    public function from_server_reads_known_request_headers(array $server, array $expected): void
    {
        $headers = HttpHeaderList::fromServer($server)->list;

        ksort($headers);
        ksort($expected);
        $this->assertSame($expected, $headers);
    }

    #[Test]
    public function from_array_applies_the_same_rules_as_set(): void
    {
        $list = HttpHeaderList::fromArray([
            'Content-Type' => ['text/plain', 'text/html'],
            'Cache-Control' => ['no-store', 'private'],
        ]);

        $this->assertSame('text/html', $list[HttpHeaders::ContentType]);
        $this->assertSame(['no-store', 'private'], $list[HttpHeaders::CacheControl]);
    }

    #[Test]
    public function from_array_rejects_values_that_could_split_the_header(): void
    {
        $this->expectException(WebError::class);
        HttpHeaderList::fromArray(['Location' => ["/x\r\nX: y"]]);
    }
}
