<?php

namespace SnipForm\Tests\Integration;

use SnipForm\Data\ChannelResult;

/**
 * Live attribution preview against a local SnipForm deployment.
 */
class AttributionTest extends IntegrationTestCase
{
    public function test_preview_with_explicit_utms(): void
    {
        $result = $this->client->attribution()->preview([
            'utm_source' => 'whatsapp',
            'utm_medium' => 'social',
            'utm_campaign' => 'spring',
        ]);

        $this->assertInstanceOf(ChannelResult::class, $result);
        $this->assertSame('messaging', $result->category);
        $this->assertSame('WhatsApp', $result->name);
        $this->assertFalse($result->isDirect());
        $this->assertFalse($result->isPaid());
    }

    public function test_preview_with_url_parses_query_string(): void
    {
        $result = $this->client->attribution()->preview([
            'url' => 'https://example.com/landing?utm_source=tg&utm_medium=messaging&utm_campaign=spring',
        ]);

        $this->assertSame('messaging', $result->category);
        $this->assertSame('Telegram', $result->name);
    }

    public function test_preview_paid_messaging_routes_to_paid(): void
    {
        // utm_medium=cpc should beat the new messaging-source branch
        $result = $this->client->attribution()->preview([
            'utm_source' => 'whatsapp',
            'utm_medium' => 'cpc',
        ]);

        $this->assertSame('paid_search', $result->category);
        $this->assertTrue($result->isPaid());
    }

    public function test_preview_asraw_returns_array(): void
    {
        $raw = $this->client->attribution()->asRaw()->preview([
            'utm_source' => 'whatsapp',
            'utm_medium' => 'social',
        ]);

        $this->assertIsArray($raw);
        $this->assertSame('messaging', $raw['channel_category']);
    }

    public function test_presets_returns_full_catalog(): void
    {
        $presets = $this->client->attribution()->presets();

        $this->assertNotEmpty($presets);
        $this->assertArrayHasKey('group', $presets[0]);
        $this->assertArrayHasKey('utm_source', $presets[0]);
        $this->assertArrayHasKey('utm_medium', $presets[0]);

        $whatsapp = array_filter($presets, fn ($p) => $p['key'] === 'whatsapp');
        $this->assertNotEmpty($whatsapp, 'WhatsApp preset should be present');
    }
}
