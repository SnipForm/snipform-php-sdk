<?php

namespace Snipform\Resources;

/**
 * Typed value object for a single short-link click event.
 */
class Click
{
    public function __construct(
        public readonly string $id,
        public readonly string $shortLinkId,
        public readonly ?string $shortLinkGroupId,
        public readonly ?int $clickTs,
        public readonly ?string $type,
        public readonly ?string $referrerDomain,
        public readonly ?string $referrerUrl,
        public readonly ?string $country,
        public readonly ?string $countryCode,
        public readonly ?string $region,
        public readonly ?string $city,
        public readonly ?string $device,
        public readonly ?string $browser,
        public readonly ?string $os,
        public readonly bool $isBot,
        public readonly ?string $botName,
        private readonly array $raw,
    ) {}

    public function raw(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->raw;
        }

        return $this->raw[$key] ?? null;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            id: (string) ($row['id'] ?? ''),
            shortLinkId: (string) ($row['short_link_id'] ?? ''),
            shortLinkGroupId: $row['short_link_group_id'] ?? null,
            clickTs: isset($row['click_ts']) ? (int) $row['click_ts'] : null,
            type: $row['type'] ?? null,
            referrerDomain: $row['referrer_domain'] ?? null,
            referrerUrl: $row['referrer_url'] ?? null,
            country: $row['country'] ?? null,
            countryCode: $row['country_code'] ?? null,
            region: $row['region'] ?? null,
            city: $row['city'] ?? null,
            device: $row['device'] ?? null,
            browser: $row['browser'] ?? null,
            os: $row['os'] ?? null,
            isBot: (bool) ($row['is_bot'] ?? false),
            botName: $row['bot_name'] ?? null,
            raw: $row,
        );
    }
}
