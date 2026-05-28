<?php

namespace SnipForm\Data;

/**
 * Typed value object for a single SignalSession row, as the V2 API ships it.
 *
 * Only the columns most consumers want are typed; the full raw row is kept
 * accessible via ->raw() for fields we haven't surfaced.
 */
class SessionRow extends SnipFormDTO
{
    public function __construct(
        public readonly string $id,
        public readonly int $entryTs,
        public readonly ?int $lastTs,
        public readonly ?string $country,
        public readonly ?string $countryCode,
        public readonly ?string $city,
        public readonly ?string $device,
        public readonly ?string $os,
        public readonly ?string $browser,
        public readonly ?string $entryPath,
        public readonly ?string $exitPath,
        public readonly ?string $referrerDomain,
        public readonly ?string $source,
        public readonly ?string $channel,
        public readonly ?string $utmSource,
        public readonly ?string $utmMedium,
        public readonly ?string $utmCampaign,
        public readonly ?string $utmContent,
        public readonly ?string $utmTerm,
        public readonly int $views,
        public readonly int $timeOnSite,
        public readonly bool $bounced,
        public readonly array $tags,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $row): self
    {
        return new self(
            id: (string) ($row['id'] ?? ''),
            entryTs: (int) ($row['entry_ts'] ?? 0),
            lastTs: isset($row['last_ts']) ? (int) $row['last_ts'] : null,
            country: $row['country_name'] ?? null,
            countryCode: $row['request_country'] ?? null,
            city: $row['city_name'] ?? null,
            device: $row['request_device'] ?? null,
            os: $row['request_platform'] ?? null,
            browser: $row['request_browser_name'] ?? null,
            entryPath: $row['entry_path'] ?? null,
            exitPath: $row['exit_path'] ?? null,
            referrerDomain: $row['referrer_domain'] ?? null,
            source: $row['source_name'] ?? null,
            channel: $row['channel_category'] ?? null,
            utmSource: $row['utm_source'] ?? null,
            utmMedium: $row['utm_medium'] ?? null,
            utmCampaign: $row['utm_campaign'] ?? null,
            utmContent: $row['utm_content'] ?? null,
            utmTerm: $row['utm_term'] ?? null,
            views: (int) ($row['views'] ?? 0),
            timeOnSite: (int) ($row['time_on_site'] ?? 0),
            bounced: (bool) ($row['bounced'] ?? false),
            tags: $row['tags'] ?? [],
            raw: $row,
        );
    }
}
