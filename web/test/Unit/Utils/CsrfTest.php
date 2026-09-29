<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Utils;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Web\Utils\Csrf;
use Vwork\Web\WebError;

final class CsrfTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        putenv('CSRF_KEY=q29bvc9tqccb43xZLmmpor2imn7ewqdf2v43x');
    }

    #[Override]
    protected function tearDown(): void
    {
        putenv('CSRF_KEY=');
    }

    #[Test]
    #[TestWith(['q2f3b9238'])]
    #[TestWith(['nlwiec'])]
    #[TestWith(['np9wyetc234879q'])]
    #[TestWith(['alvnwtb4erg655'])]
    public function create_generates_identical_keys_for_same_id(string $id): void
    {
        $expect = Csrf::create($id);
        $result = Csrf::create($id);
        $this->assertSame($expect, $result);
    }

    #[Test]
    #[TestWith(['q2f3b9238'])]
    #[TestWith(['nlwiec'])]
    #[TestWith(['np9wyetc234879q'])]
    #[TestWith(['alvnwtb4erg655'])]
    public function verify_returns_true_on_identical_tokens(string $id): void
    {
        $token = Csrf::create($id);
        $result = Csrf::verify($id, $token);
        $this->assertTrue($result);
    }

    #[Test]
    #[TestWith(['q2f3b9238', 'nlwiec'])]
    #[TestWith(['np9wyetc234879q', 'alvnwtb4erg655'])]
    public function verify_returns_false_on_different_tokens(string $id1, string $id2): void
    {
        $token = Csrf::create($id1);
        $result = Csrf::verify($id2, $token);
        $this->assertFalse($result);
    }

    #[Test]
    #[TestWith(['q2f3b9238'])]
    #[TestWith(['nlwiec'])]
    #[TestWith(['np9wyetc234879q'])]
    #[TestWith(['alvnwtb4erg655'])]
    public function throws_weberror_when_env_is_not_valid(string $id): void
    {
        putenv('CSRF_KEY=');
        $this->expectException(WebError::class);
        Csrf::create($id);
    }
}
