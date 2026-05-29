<?php

namespace SnipForm\Resources;

use SnipForm\Concerns\RawAware;
use SnipForm\Data\ChannelResult;
use SnipForm\Http\HttpClient;

/**
 * Attribution diagnostics + UX catalog.
 *
 *  - `preview(...)` runs SnipForm's channel attribution engine against an
 *    arbitrary set of UTM tags (or a full destination URL). Lets you ask
 *    "if a visitor landed with these tags right now, what channel would
 *    they get pegged to?" — perfect for a "Test attribution" button in a
 *    link-builder UI, or for verifying that a campaign's tags map where
 *    you expect them to.
 *
 *  - `presets()` returns the catalog of channel preset chips (WhatsApp,
 *    Telegram, Google Ads, Newsletter, …) used by the SnipForm app's link
 *    create/edit form. Partner apps can render the same chip strip so the
 *    UTM taxonomy stays consistent across surfaces.
 *
 * Append `->asRaw()` anywhere to skip DTO hydration and get arrays back.
 */
class Attribution
{
    use RawAware;

    public function __construct(private readonly HttpClient $http) {}

    /**
     * Preview how the attribution engine would classify a set of tags.
     *
     * Supply EITHER:
     *   - `url` — a full destination URL; UTMs are parsed from the query string.
     *   - individual UTM keys — `utm_source`, `utm_medium`, `utm_campaign`,
     *     `utm_content`, `utm_term`.
     *   - `click_ids` — `['gclid' => '...', 'fbclid' => '...']` for ad-platform clicks.
     *   - `referrer` — full referrer URL when simulating a referral.
     *
     * If both `url` and explicit UTM keys are given, the explicit keys win
     * (so you can overlay a one-off tweak on a captured URL).
     *
     * @param  array{
     *     url?: string,
     *     utm_source?: string,
     *     utm_medium?: string,
     *     utm_campaign?: string,
     *     utm_content?: string,
     *     utm_term?: string,
     *     click_ids?: array<string, string>,
     *     referrer?: string,
     * }  $tags
     */
    public function preview(array $tags): ChannelResult|array
    {
        $row = (array) $this->http
            ->post('property/attribution/preview', $tags)
            ->data('preview');

        return $this->hydrate($row, ChannelResult::fromArray(...));
    }

    /**
     * Channel preset catalog — one entry per chip in the SnipForm link
     * builder's preset picker. Each entry carries `group`, `key`, `label`,
     * `utm_source`, `utm_medium` — render them as chips, splat the UTMs
     * into your `links()->create()` call when one is clicked.
     *
     * Always returns the raw array (the catalog is shaped for UI consumption
     * directly; there's no value in hydrating each row).
     *
     * @return array<int, array{group: string, key: string, label: string, utm_source: string, utm_medium: string}>
     */
    public function presets(): array
    {
        return (array) ($this->http
            ->get('property/attribution/presets')
            ->data('presets') ?? []);
    }
}
