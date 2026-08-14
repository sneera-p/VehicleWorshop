<?php

declare(strict_types=1);

namespace Vwork\Shared\Validator\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vwork\Shared\Validator\Rules\Email;

final class EmailTest extends TestCase
{
    private Email $rule;

    protected function setUp(): void
    {
        $this->rule = new Email();
    }

    #[DataProvider('validEmails')]
    public function testPassesValidEmails(string $email): void
    {
        $this->assertTrue($this->rule->passes($email));
    }

    #[DataProvider('invalidEmails')]
    public function testRejectsInvalidEmails(string $email): void
    {
        $this->assertFalse($this->rule->passes($email));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validEmails(): array
    {
        return [
            'simple address' => ['test@example.com'],
            'with subdomain' => ['user@mail.example.com'],
            'with plus tag' => ['user+tag@example.com'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidEmails(): array
    {
        return [
            'missing @' => ['not-an-email'],
            'missing domain' => ['user@'],
            'missing local part' => ['@example.com'],
            'empty string' => [''],
        ];
    }
}
