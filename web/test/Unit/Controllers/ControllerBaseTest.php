<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Controllers;

use Closure;
use JsonException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Shared\Types\Cast;
use Vwork\Web\Http\Headers\HttpHeaders;
use Vwork\Web\Http\HttpStatus;
use Vwork\Web\Http\Response;
use Vwork\Web\Test\Stubs\ControllerBaseStub;
use Vwork\Web\WebError;

final class ControllerBaseTest extends TestCase
{
    private string|false $previousViewPath;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->previousViewPath = getenv('VIEW_PATH');
        putenv('VIEW_PATH=web/test/Fixtures/Views');
    }

    #[Override]
    protected function tearDown(): void
    {
        putenv($this->previousViewPath === false ? 'VIEW_PATH' : "VIEW_PATH={$this->previousViewPath}");
        parent::tearDown();
    }

    private static function body(Response $response): string
    {
        ob_start();
        $response->send();
        return (string) ob_get_clean();
    }

    #[Test]
    public function view_wraps_the_template_in_the_layout(): void
    {
        $response = new ControllerBaseStub()->callView('vars', ['name' => 'Sneze', 'count' => 3, 'title' => 'Jobs'], 'layout');

        $this->assertSame(
            "<title>Jobs</title>\n<main>Sneze has 3 jobs\n</main>\nisolated",
            self::body($response),
        );
    }

    #[Test]
    public function view_falls_back_to_a_default_title(): void
    {
        $response = new ControllerBaseStub()->callView('hello', [], 'layout');

        $this->assertStringStartsWith('<title>No Title</title>', self::body($response));
    }

    #[Test]
    public function view_passes_only_content_and_title_to_the_layout(): void
    {
        $response = new ControllerBaseStub()->callView('vars', ['name' => 'Sneze', 'count' => 3], 'layout');

        $this->assertStringEndsWith('isolated', self::body($response));
    }

    #[Test]
    public function view_uses_the_given_status_with_a_layout(): void
    {
        $response = new ControllerBaseStub()->callView('hello', [], 'layout', HttpStatus::NotFound);

        $this->assertSame(HttpStatus::NotFound, $response->status);
        $this->assertSame(['Content-Type' => ['text/html; charset=utf-8']], $response->headers->list);
    }

    #[Test]
    public function view_throws_for_a_missing_layout(): void
    {
        $this->expectException(WebError::class);
        new ControllerBaseStub()->callView('hello', [], 'does_not_exist');
    }

    private const array JSON = ['Content-Type' => ['application/json; charset=utf-8'], 'Vary' => ['Accept']];
    private const array CBOR = ['Content-Type' => ['application/cbor'], 'Vary' => ['Accept']];

    /**
     * @param array<string, mixed> $data
     */
    #[Test]
    #[TestWith([['id' => 42], '{"id":42}'])]
    #[TestWith([[], '[]'])]
    #[TestWith([['url' => '/jobs/1', 'name' => 'රසීද', 'sep' => "\u{2028}"], "{\"url\":\"/jobs/1\",\"name\":\"රසීද\",\"sep\":\"\u{2028}\"}"])]
    public function payload_sends_json(array $data, string $expected): void
    {
        $response = new ControllerBaseStub()->callPayload($data, ['application/json']);

        $this->assertSame(HttpStatus::Ok, $response->status);
        $this->assertSame(self::JSON, $response->headers->list);
        $this->assertSame($expected, self::body($response));
    }

    /**
     * @param array<string, mixed> $data
     */
    #[Test]
    #[TestWith([['id' => 42], "\xa1\x62id\x18\x2a"])] // map(1), text(2) "id", uint 42
    #[TestWith([[], "\x80"])]                          // empty PHP array is a list
    public function payload_sends_cbor(array $data, string $expected): void
    {
        $response = new ControllerBaseStub()->callPayload($data, ['application/cbor']);

        $this->assertSame(HttpStatus::Ok, $response->status);
        $this->assertSame(self::CBOR, $response->headers->list);
        $this->assertSame($expected, self::body($response));
    }

    /**
     * @param list<string> $accept
     * @param non-empty-string $expectedType
     */
    #[Test]
    #[TestWith([[], 'application/json'])]                                                   // no Accept header
    #[TestWith([['*/*'], 'application/json'])]                                              // fetch()/curl default
    #[TestWith([['application/*'], 'application/json'])]
    #[TestWith([['APPLICATION/CBOR'], 'application/cbor'])]                                 // media types are case-insensitive
    #[TestWith([['text/html', 'application/cbor'], 'application/cbor'])]                    // unsupported types skipped
    #[TestWith([['application/json;q=0.5', 'application/cbor;q=0.9'], 'application/cbor'])] // higher q wins
    #[TestWith([['application/cbor;q=0.9', 'application/json'], 'application/json'])]       // missing q means 1
    #[TestWith([['*/*', 'application/cbor'], 'application/cbor'])]                          // specific beats wildcard at equal q
    #[TestWith([['application/cbor', 'application/json;q=0.1'], 'application/cbor'])]       // header split over several lines
    #[TestWith([['application/cbor;q=0', 'application/json'], 'application/json'])]         // q=0 means "not acceptable"
    public function payload_negotiates_the_type_from_accept(array $accept, string $expectedType): void
    {
        $response = new ControllerBaseStub()->callPayload(['id' => 1], $accept);

        $this->assertSame(HttpStatus::Ok, $response->status);
        $this->assertStringStartsWith($expectedType, Cast::string($response->headers[HttpHeaders::ContentType]));
    }

    /**
     * @param list<string> $accept
     */
    #[Test]
    #[TestWith([['text/xml']])]
    #[TestWith([['application/json;q=0, application/cbor;q=0']])]
    public function payload_answers_406_when_no_supported_type_is_acceptable(array $accept): void
    {
        $response = new ControllerBaseStub()->callPayload(['id' => 1], $accept);

        $this->assertSame(HttpStatus::NotAcceptable, $response->status);
        $this->assertSame(['Accept'], $response->headers->list['Vary']);
        $this->assertSame('Supported: application/json, application/cbor', self::body($response));
    }

    #[Test]
    public function payload_uses_the_given_status(): void
    {
        $response = new ControllerBaseStub()->callPayload(['error' => 'taken'], [], HttpStatus::Conflict);

        $this->assertSame(HttpStatus::Conflict, $response->status);
    }

    #[Test]
    public function payload_throws_on_data_json_cannot_hold(): void
    {
        $this->expectException(JsonException::class);
        new ControllerBaseStub()->callPayload(['bad' => "\xB1\x31"], ['application/json']); // invalid UTF-8
    }

    #[Test]
    public function payload_throws_on_data_cbor_text_cannot_hold(): void
    {
        $this->expectException(\Cbor\Exception::class);
        $this->expectExceptionCode(CBOR_ERROR_UTF8);
        new ControllerBaseStub()->callPayload(['bad' => "\xB1\x31"], ['application/cbor']);
    }

    #[Test]
    public function sse_sets_stream_headers(): void
    {
        $response = new ControllerBaseStub()->callSse(static function (Closure $emit): void {
        });

        $this->assertSame(HttpStatus::Ok, $response->status);
        $this->assertSame([
            'Content-Type' => ['text/event-stream; charset=utf-8'],
            'Cache-Control' => ['no-cache'],
            'X-Accel-Buffering' => ['no'],
        ], $response->headers->list);
    }

    #[Test]
    public function sse_runs_the_source_only_when_sent(): void
    {
        $calls = 0;
        $response = new ControllerBaseStub()->callSse(static function (Closure $emit) use (&$calls): void {
            $calls++;
        });

        $this->assertSame(0, $calls);
        self::body($response);
        $this->assertSame(1, $calls);
    }

    /**
     * @param list<array{string, array<array-key, mixed>}> $events
     */
    #[Test]
    #[TestWith([[], ''])]                                                                          // no events, no output
    #[TestWith([[['job.updated', ['id' => 1]]], "event: job.updated\ndata: {\"id\":1}\n\n"])]
    #[TestWith([[['a', ['n' => 1]], ['b', ['n' => 2]]], "event: a\ndata: {\"n\":1}\n\nevent: b\ndata: {\"n\":2}\n\n"])]
    #[TestWith([[['note', ['text' => "line1\nline2"]]], "event: note\ndata: {\"text\":\"line1\\nline2\"}\n\n"])] // newline escaped, stays one line
    #[TestWith([[['note', ['text' => "a\r\nb"]]], "event: note\ndata: {\"text\":\"a\\r\\nb\"}\n\n"])]          // \r escaped too
    #[TestWith([[['note', []]], "event: note\ndata: []\n\n"])]                                     // empty array is a JSON list
    #[TestWith([[['link', ['url' => '/jobs/1', 'name' => 'රසීද']]], "event: link\ndata: {\"url\":\"/jobs/1\",\"name\":\"රසීද\"}\n\n"])] // slashes and unicode unescaped
    #[TestWith([[['note', ['s' => "a\u{2028}b"]]], "event: note\ndata: {\"s\":\"a\\u2028b\"}\n\n"])]      // line terminators stay escaped
    public function sse_frames_each_emitted_event_as_json(array $events, string $expected): void
    {
        $response = new ControllerBaseStub()->callSse(static function (Closure $emit) use ($events): void {
            foreach ($events as [$event, $data]) {
                $emit($event, $data);
            }
        });

        $this->assertSame($expected, self::body($response));
    }

    #[Test]
    public function sse_throws_on_data_json_cannot_hold(): void
    {
        $response = new ControllerBaseStub()->callSse(static function (Closure $emit): void {
            $emit('bad', ['text' => "\xB1\x31"]); // invalid UTF-8
        });

        $this->expectException(JsonException::class);

        ob_start();
        try {
            $response->send();
        } finally {
            ob_end_clean(); // close the buffer even when send() throws, or PHPUnit flags the test as risky
        }
    }
}
