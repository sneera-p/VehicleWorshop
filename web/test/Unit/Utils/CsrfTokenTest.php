<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Utils;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Vwork\Web\Utils\CsrfToken;

final class CsrfTokenTest extends TestCase
{
    private const string SECRET = 'q29bvc9tqccb43xZLmmpor2imn7ewqdf2v43x';

    private static function csrf(string $secret = self::SECRET): CsrfToken
    {
        return new CsrfToken($secret, new Randomizer());
    }

    #[Test]
    #[TestWith(['q2f3b9238'])]
    #[TestWith(['nlwiec'])]
    #[TestWith([''])]
    public function a_created_token_verifies_for_its_own_session(string $id): void
    {
        $csrf = self::csrf();

        $this->assertTrue($csrf->verify($id, $csrf->create($id)));
    }

    #[Test]
    public function the_same_session_gets_a_different_looking_token_every_time(): void
    {
        $csrf = self::csrf();
        $a = $csrf->create('session');
        $b = $csrf->create('session');

        // masked: never equal, both valid
        $this->assertNotSame($a, $b);
        $this->assertTrue($csrf->verify('session', $a));
        $this->assertTrue($csrf->verify('session', $b));
    }

    #[Test]
    public function the_mask_comes_from_the_injected_randomizer(): void
    {
        $a = new CsrfToken(self::SECRET, new Randomizer(new Xoshiro256StarStar(42)));
        $b = new CsrfToken(self::SECRET, new Randomizer(new Xoshiro256StarStar(42)));

        $this->assertSame($a->create('session'), $b->create('session'));
    }

    #[Test]
    public function tokens_are_url_safe_base64_without_padding(): void
    {
        // 32-byte mask + 32-byte masked HMAC = 64 bytes = 86 chars
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{86}$/', self::csrf()->create('session'));
    }

    #[Test]
    #[TestWith(['q2f3b9238', 'nlwiec'])]
    #[TestWith(['session', 'session '])]
    #[TestWith(['session', ''])]
    public function a_token_does_not_verify_for_another_session(string $owner, string $other): void
    {
        $csrf = self::csrf();

        $this->assertFalse($csrf->verify($other, $csrf->create($owner)));
    }

    #[Test]
    public function a_token_does_not_verify_under_another_secret(): void
    {
        $token = self::csrf()->create('session');

        $this->assertFalse(self::csrf('another-secret-entirely-0123456789')->verify('session', $token));
    }

    #[Test]
    #[TestWith([0])]  // inside the mask
    #[TestWith([50])] // inside the masked HMAC
    #[TestWith([84])] // near the end (the last char also carries unused padding bits)
    public function a_tampered_token_does_not_verify(int $at): void
    {
        $csrf = self::csrf();
        $token = $csrf->create('session');
        $token[$at] = $token[$at] === 'A' ? 'B' : 'A';

        $this->assertFalse($csrf->verify('session', $token));
    }

    #[Test]
    #[TestWith([''])]
    #[TestWith(['not base64 at all!'])]
    #[TestWith(['c2hvcnQ'])] // valid base64, too short
    public function malformed_tokens_are_rejected_not_thrown(string $token): void
    {
        $this->assertFalse(self::csrf()->verify('session', $token));
    }

    #[Test]
    public function a_token_with_extra_bytes_is_rejected(): void
    {
        $csrf = self::csrf();

        $this->assertFalse($csrf->verify('session', $csrf->create('session') . 'AAAA'));
    }
}
