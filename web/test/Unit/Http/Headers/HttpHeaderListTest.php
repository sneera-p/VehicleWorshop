<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Http\Headers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Web\Http\Headers\HttpHeaderList;
use Vwork\Web\Http\Headers\RequestHeaders;
use Vwork\Web\Http\Headers\ResponseHeaders;
use Vwork\Web\WebError;

/**
 * Set/get/iterate rules are shared, so they're tested on the response side
 * (the side the app writes to). fromServer covers the request side.
 */
final class HttpHeaderListTest extends TestCase
{
    /**
     * @return HttpHeaderList<ResponseHeaders>
     */
    private static function empty(): HttpHeaderList
    {
        return HttpHeaderList::fromArray([]);
    }

    #[Test]
    public function single_value_headers_replace_on_set(): void
    {
        $list = self::empty();
        $list[ResponseHeaders::ContentType] = 'text/plain';
        $list[ResponseHeaders::ContentType] = 'text/html';

        $this->assertSame('text/html', $list[ResponseHeaders::ContentType]);
        $this->assertSame(['Content-Type' => ['text/html']], $list->list);
    }

    #[Test]
    #[TestWith([ResponseHeaders::CacheControl, 'no-store', 'private'])]
    #[TestWith([ResponseHeaders::Vary, 'Accept', 'Cookie'])]
    #[TestWith([ResponseHeaders::Allow, 'GET', 'POST'])]
    public function list_headers_append_on_set(ResponseHeaders $header, string $first, string $second): void
    {
        $list = self::empty();
        $list[$header] = $first;
        $list[$header] = $second;

        $this->assertSame([$first, $second], $list[$header]);
    }

    #[Test]
    public function missing_headers_read_as_null_and_do_not_exist(): void
    {
        $list = self::empty();

        $this->assertNull($list[ResponseHeaders::Location]);
        $this->assertFalse(isset($list[ResponseHeaders::Location]));
    }

    #[Test]
    public function unset_removes_every_value(): void
    {
        $list = self::empty();
        $list[ResponseHeaders::CacheControl] = 'no-store';
        $list[ResponseHeaders::CacheControl] = 'private';

        $this->assertTrue(isset($list[ResponseHeaders::CacheControl]));
        unset($list[ResponseHeaders::CacheControl]);

        $this->assertFalse(isset($list[ResponseHeaders::CacheControl]));
        $this->assertSame([], $list->list);
    }

    #[Test]
    #[TestWith(["/jobs\r\nSet-Cookie: session_token=evil"])]
    #[TestWith(["/jobs\nX: y"])]
    #[TestWith(["/jobs\r"])]
    #[TestWith(["/jobs\0"])]
    public function rejects_values_that_could_split_the_header(string $value): void
    {
        $list = self::empty();

        $this->expectException(WebError::class);
        $list[ResponseHeaders::Location] = $value;
    }

    #[Test]
    public function to_lines_emits_one_line_per_value(): void
    {
        $list = self::empty();
        $list[ResponseHeaders::ContentType] = 'text/html';
        $list[ResponseHeaders::CacheControl] = 'no-store';
        $list[ResponseHeaders::CacheControl] = 'private';

        $this->assertSame(
            ['Content-Type: text/html', 'Cache-Control: no-store', 'Cache-Control: private'],
            iterator_to_array($list->toLines(), false),
        );
    }

    #[Test]
    public function iterates_with_enum_keys(): void
    {
        $list = self::empty();
        $list[ResponseHeaders::ContentType] = 'text/html';
        $list[ResponseHeaders::Allow] = 'GET';

        $seen = [];
        foreach ($list as $header => $values) {
            $seen[] = [$header, $values];
        }

        $this->assertSame([
            [ResponseHeaders::ContentType, ['text/html']],
            [ResponseHeaders::Allow, ['GET']],
        ], $seen);
    }

    #[Test]
    public function request_lists_iterate_with_request_enum_keys(): void
    {
        $list = HttpHeaderList::fromServer(['HTTP_ORIGIN' => 'https://vwork.test']);

        foreach ($list as $header => $values) {
            $this->assertSame(RequestHeaders::Origin, $header);
            $this->assertSame(['https://vwork.test'], $values);
        }
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
    // dashes become underscores, case follows the enum value
    #[TestWith([['HTTP_X_CSRF_TOKEN' => 'tok', 'HTTP_HOST' => 'vwork.test', 'HTTP_ORIGIN' => 'https://vwork.test'], ['X-CSRF-Token' => ['tok'], 'Host' => ['vwork.test'], 'Origin' => ['https://vwork.test']]])]
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
    public function from_server_returns_a_single_value_for_non_list_headers(): void
    {
        $list = HttpHeaderList::fromServer(['HTTP_X_CSRF_TOKEN' => 'tok', 'HTTP_ACCEPT' => 'text/html']);

        $this->assertSame('tok', $list[RequestHeaders::XCsrfToken]);
        $this->assertSame(['text/html'], $list[RequestHeaders::Accept]);
    }

    #[Test]
    public function from_array_applies_the_same_rules_as_set(): void
    {
        $list = HttpHeaderList::fromArray([
            'Content-Type' => ['text/plain', 'text/html'],
            'Cache-Control' => ['no-store', 'private'],
        ]);

        $this->assertSame('text/html', $list[ResponseHeaders::ContentType]);
        $this->assertSame(['no-store', 'private'], $list[ResponseHeaders::CacheControl]);
    }

    #[Test]
    public function from_array_rejects_values_that_could_split_the_header(): void
    {
        $this->expectException(WebError::class);
        HttpHeaderList::fromArray(['Location' => ["/x\r\nX: y"]]);
    }

    #[Test]
    public function from_array_rejects_request_only_headers(): void
    {
        $this->expectException(\ValueError::class);
        /** @phpstan-ignore argument.type */
        HttpHeaderList::fromArray(['Authorization' => ['Bearer x']]);
    }
}
