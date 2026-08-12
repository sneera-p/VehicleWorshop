<?php

declare(strict_types=1);

namespace Vwork\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Placeholder — proves the "integration" testsuite is wired correctly
 * end-to-end (tools/phpunit.xml.dist -> this directory -> a passing run),
 * same as EmailTest did for the "unit" suite. Replace with real tests once
 * there's a module to integration-test against.
 *
 * A real integration test here would talk to the actual running services
 * — e.g. connect to Postgres using DB_HOST/DB_PORT/DB_USER/DB_PASSWORD/
 * DB_NAME, or Valkey using CACHE_HOST/CACHE_PORT/CACHE_PASSWORD (see
 * docker-compose.yml's "app" service for these) — which is exactly why
 * this suite is deliberately excluded from pre-commit/pre-push: it needs
 * `docker compose up` running first, and a git hook shouldn't assume that.
 */
final class DummyIntegrationTest extends TestCase
{
    public function testSuiteIsWired(): void
    {
        $this->assertTrue(true);
    }
}
