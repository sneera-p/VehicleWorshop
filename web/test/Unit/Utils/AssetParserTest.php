<?php

declare(strict_types=1);

namespace Vwork\Web\Test\Unit\Utils;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Vwork\Web\Utils\AssetParser;
use Vwork\Web\WebError;

final class AssetParserTest extends TestCase
{
    private const string DIR = 'web/test/Fixtures/Assets/';

    #[Test]
    public function maps_each_source_path_to_its_url_under_assets(): void
    {
        $this->assertSame([
            'css/staff.css' => '/assets/staff-x4j2av4s.css',
            'static/img/logo.png' => '/assets/static/img/logo-7zjyd5xx.png',
        ], new AssetParser(self::DIR . 'assets.json')->urls);
    }

    #[Test]
    #[TestWith(['missing.json'])]   // the build hasn't run
    #[TestWith(['invalid.json'])]   // half-written or corrupted
    #[TestWith(['scalar.json'])]    // valid JSON, but not an object
    public function rejects_a_manifest_it_cannot_use(string $file): void
    {
        $this->expectException(WebError::class);
        new AssetParser(self::DIR . $file);
    }
}
