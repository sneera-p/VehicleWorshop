<?php

declare(strict_types=1);

namespace Vwork\Web\Http\Cookies;

use Vwork\Web\WebError;

final readonly class ResponseCookie
{
    /**
     * @throws WebError if $path could inject attributes, or $maxAge is negative
     */
    public function __construct(
        public HttpCookies $name,
        public string $value,
        public string $path = '/',
        public CookieSitePolicies $sameSite = CookieSitePolicies::Lax,
        public ?int $maxAge = null,
    ) {
        if (strpbrk($path, ";,\r\n\0 ") !== false || !str_starts_with($path, '/')) {
            throw new WebError("Invalid cookie path: {$path}");
        }

        if ($maxAge !== null && $maxAge < 0) {
            throw new WebError('Cookie Max-Age cannot be negative');
        }
    }

    public function toLine(bool $secure): string
    {
        $line = "{$this->name->value}=" . rawurlencode($this->value)
            . "; Path={$this->path}"
            . "; SameSite={$this->sameSite->value}"
            . '; HttpOnly';

        if ($this->maxAge !== null) {
            $line .= "; Max-Age={$this->maxAge}";
        }

        if ($secure) {
            $line .= '; Secure';
        }

        return $line;
    }
}
