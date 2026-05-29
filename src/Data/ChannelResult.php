<?php

namespace SnipForm\Data;

/**
 * Result of running attribution against a set of UTM tags (or a full URL).
 * Returned by `$client->attribution()->preview(...)`. Mirrors the shape the
 * tracker would emit for a real session with the same tags.
 *
 *   $r = $client->attribution()->preview([
 *       'url' => 'https://example.com?utm_source=whatsapp&utm_medium=social',
 *   ]);
 *
 *   $r->category;       // 'messaging'
 *   $r->categoryLabel;  // 'Messaging'
 *   $r->name;           // 'WhatsApp'
 *   $r->source;         // 'whatsapp'
 *   $r->medium;         // 'social'
 *   $r->method;         // 'utm' — how the engine matched
 */
class ChannelResult extends SnipFormDTO
{
    public function __construct(
        public readonly string $category,
        public readonly string $categoryLabel,
        public readonly ?string $categoryColor,
        public readonly string $name,
        public readonly string $source,
        public readonly string $medium,
        public readonly ?string $campaign,
        public readonly string $method,
        public readonly ?string $clickId,
        public readonly ?string $customRule,
    ) {}

    public function isDirect(): bool
    {
        return $this->category === 'direct';
    }

    public function isPaid(): bool
    {
        return str_starts_with($this->category, 'paid_');
    }

    public static function fromArray(array $row): self
    {
        return new self(
            category: (string) ($row['channel_category'] ?? 'direct'),
            categoryLabel: (string) ($row['channel_category_label'] ?? ''),
            categoryColor: $row['channel_category_color'] ?? null,
            name: (string) ($row['channel_name'] ?? ''),
            source: (string) ($row['source'] ?? ''),
            medium: (string) ($row['medium'] ?? ''),
            campaign: $row['campaign'] ?? null,
            method: (string) ($row['attribution_method'] ?? 'direct'),
            clickId: $row['click_id'] ?? null,
            customRule: $row['custom_rule'] ?? null,
        );
    }
}
