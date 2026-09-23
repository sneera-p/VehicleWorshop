<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Web\Http\HttpHeaders;
use Vwork\Web\Test\Stubs\StubHttpMessage;

final class HttpMessageTest extends TestCase
{
    /**
     * @param array<value-of<HttpHeaders>, list<string>> $headers
     */
    #[Test]
    #[TestWith([[
        HttpHeaders::Accept->value => ['a', 'b', 'c', 'd'],
        HttpHeaders::Allow->value => ['f', 'g', 'h', 'i']
    ]])]
    public function has_header_returns_true_on_not_empty(array $headers): void
    {
        $msg = new StubHttpMessage($headers);
        foreach (array_keys($headers) as $key) {
            $this->assertTrue($msg->hasHeader(HttpHeaders::from($key)));
        }
    }

    /**
     * @param array<value-of<HttpHeaders>, list<string>> $headers
     */
    #[Test]
    #[TestWith([[
        HttpHeaders::Authorization->value => [],
        HttpHeaders::CacheControl->value => []
    ]])]
    public function has_header_returns_false_on_empty(array $headers): void
    {
        $msg = new StubHttpMessage($headers);
        foreach (array_keys($headers) as $key) {
            $this->assertFalse($msg->hasHeader(HttpHeaders::from($key)));
        }
    }

    #[Test]
    public function has_header_returns_false_on_notset(): void
    {
        $msg = new StubHttpMessage();
        foreach (HttpHeaders::cases() as $key) {
            $this->assertFalse($msg->hasHeader($key));
        }
    }


    #[Test]
    public function add_header_creates_new_list_with_single_value(): void
    {
        $msg = new StubHttpMessage();
        $msg->addHeader(HttpHeaders::Accept, 'text/html');

        $this->assertSame(['text/html'], $msg->headers[HttpHeaders::Accept->value]);
    }

    #[Test]
    public function add_header_appends_rather_than_overwrites(): void
    {
        $msg = new StubHttpMessage();
        $msg->addHeader(HttpHeaders::Allow, 'GET');
        $msg->addHeader(HttpHeaders::Allow, 'POST');

        $this->assertSame(['GET', 'POST'], $msg->headers[HttpHeaders::Allow->value]);
    }

    #[Test]
    public function add_header_keeps_different_keys_independent(): void
    {
        $msg = new StubHttpMessage();
        $msg->addHeader(HttpHeaders::Accept, 'text/html');
        $msg->addHeader(HttpHeaders::Allow, 'GET');

        $this->assertSame(['text/html'], $msg->headers[HttpHeaders::Accept->value]);
        $this->assertSame(['GET'], $msg->headers[HttpHeaders::Allow->value]);
    }

    #[Test]
    public function add_header_returns_self(): void
    {
        $msg = new StubHttpMessage();

        $this->assertSame($msg, $msg->addHeader(HttpHeaders::Accept, 'text/html'));
    }

    #[Test]
    public function rm_header_removes_a_header_that_was_set(): void
    {
        $msg = new StubHttpMessage();
        $msg->addHeader(HttpHeaders::Accept, 'text/html');
        $msg->rmHeader(HttpHeaders::Accept);

        $this->assertArrayNotHasKey(HttpHeaders::Accept->value, $msg->headers);
    }

    #[Test]
    public function rm_header_on_a_header_that_was_never_set_does_not_throw(): void
    {
        $msg = new StubHttpMessage();
        $msg->rmHeader(HttpHeaders::Accept);

        $this->assertArrayNotHasKey(HttpHeaders::Accept->value, $msg->headers);
    }

    #[Test]
    public function rm_header_leaves_other_headers_untouched(): void
    {
        $msg = new StubHttpMessage();
        $msg->addHeader(HttpHeaders::Accept, 'text/html');
        $msg->addHeader(HttpHeaders::Allow, 'GET');
        $msg->rmHeader(HttpHeaders::Accept);

        $this->assertArrayNotHasKey(HttpHeaders::Accept->value, $msg->headers);
        $this->assertSame(['GET'], $msg->headers[HttpHeaders::Allow->value]);
    }

    #[Test]
    public function rm_header_returns_self(): void
    {
        $msg = new StubHttpMessage();

        $this->assertSame($msg, $msg->rmHeader(HttpHeaders::Accept));
    }
}
