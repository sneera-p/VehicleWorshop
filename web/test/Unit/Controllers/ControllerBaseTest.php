<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Controllers;

use Closure;
use JsonException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
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
    public function view_renders_the_template_as_html(): void
    {
        $response = new ControllerBaseStub()->callView('vars', ['name' => 'Sneze', 'count' => 3]);

        $this->assertSame(HttpStatus::Ok, $response->status);
        $this->assertSame(['Content-Type' => ['text/html; charset=utf-8']], $response->headers->list);
        $this->assertSame("Sneze has 3 jobs\n", self::body($response));
    }

    #[Test]
    public function view_uses_the_given_status(): void
    {
        $response = new ControllerBaseStub()->callView('hello', [], HttpStatus::NotFound);

        $this->assertSame(HttpStatus::NotFound, $response->status);
    }

    #[Test]
    public function view_throws_for_a_missing_template(): void
    {
        $this->expectException(WebError::class);
        new ControllerBaseStub()->callView('does_not_exist');
    }

    /**
     * @param array<string, mixed> $data
     */
    #[Test]
    #[TestWith([['id' => 42], '{"id":42}'])]
    #[TestWith([[], '[]'])]
    #[TestWith([['url' => '/jobs/1', 'name' => 'රසීද', 'sep' => "\u{2028}"], "{\"url\":\"/jobs/1\",\"name\":\"රසීද\",\"sep\":\"\u{2028}\"}"])]
    public function payload_sends_json(array $data, string $expected): void
    {
        $response = new ControllerBaseStub()->callPayload($data);

        $this->assertSame(HttpStatus::Ok, $response->status);
        $this->assertSame(['Content-Type' => ['application/json; charset=utf-8']], $response->headers->list);
        $this->assertSame($expected, self::body($response));
    }

    #[Test]
    public function payload_uses_the_given_status(): void
    {
        $response = new ControllerBaseStub()->callPayload(['error' => 'taken'], HttpStatus::Conflict);

        $this->assertSame(HttpStatus::Conflict, $response->status);
    }

    #[Test]
    public function payload_throws_on_data_json_cannot_hold(): void
    {
        $this->expectException(JsonException::class);
        new ControllerBaseStub()->callPayload(['bad' => "\xB1\x31"]); // invalid UTF-8
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
     * @param list<array{string, string}> $events
     */
    #[Test]
    #[TestWith([[], ''])]
    #[TestWith([[['job.updated', '{"id":1}']], "event: job.updated\ndata: {\"id\":1}\n\n"])]
    #[TestWith([[['a', '1'], ['b', '2']], "event: a\ndata: 1\n\nevent: b\ndata: 2\n\n"])]
    #[TestWith([[['note', "line1\nline2"]], "event: note\ndata: line1\ndata: line2\n\n"])]
    #[TestWith([[['note', '']], "event: note\ndata: \n\n"])]
    public function sse_frames_each_emitted_event(array $events, string $expected): void
    {
        $response = new ControllerBaseStub()->callSse(static function (Closure $emit) use ($events): void {
            foreach ($events as [$event, $data]) {
                $emit($event, $data);
            }
        });

        $this->assertSame($expected, self::body($response));
    }

    #[Test]
    public function file_sends_the_file_as_a_download(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'vwork_');
        file_put_contents($path, 'contents');

        try {
            $response = new ControllerBaseStub()->callFile($path, 'invoice.pdf');

            $this->assertSame(['attachment; filename="invoice.pdf"; filename*=UTF-8\'\'invoice.pdf'], $response->headers->list['Content-Disposition']);
            $this->assertSame('contents', self::body($response));
        } finally {
            unlink($path);
        }
    }
}
