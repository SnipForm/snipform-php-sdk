<?php

namespace SnipForm\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SnipForm\Client;

/**
 * Base for live-API integration tests. Skips when SNIPFORM_TEST_TOKEN isn't
 * set so a fresh clone can still run unit tests in CI without secrets.
 *
 * Configure by copying tests/.env.testing.example to tests/.env.testing
 * and filling in your local dev values. tests/bootstrap.php loads it.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected Client $client;

    protected function setUp(): void
    {
        $token = getenv('SNIPFORM_TEST_TOKEN');
        if (! $token) {
            $this->markTestSkipped('Set SNIPFORM_TEST_TOKEN in tests/.env.testing to run integration tests.');
        }

        $this->client = new Client($token, [
            'base_url' => getenv('SNIPFORM_TEST_BASE_URL') ?: 'https://app.snipform.io',
            'verify_ssl' => (bool) (getenv('SNIPFORM_TEST_VERIFY_SSL') ?: '1'),
        ]);
    }
}
