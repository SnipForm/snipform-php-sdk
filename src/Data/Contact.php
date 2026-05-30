<?php

namespace SnipForm\Data;

/**
 * Typed value object for an identified Contact.
 *
 * `meta` is an array of `['key' => ..., 'value' => ...]` pairs — same shape
 * as `signal_session.tags` so the wire stays consistent. PII (name / email /
 * phone) lives here on the Contact; sessions reference by `id` only.
 */
class Contact extends SnipFormDTO
{
    /**
     * @param  list<array{key: string, value: string|null}>  $meta
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $externalId,
        public readonly ?string $email,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly string $fullName,
        public readonly ?string $phone,
        public readonly ?string $company,
        public readonly ?string $jobTitle,
        public readonly ?string $website,
        public readonly ?string $country,
        public readonly ?string $city,
        public readonly string $lifecycleStage,
        public readonly string $state,
        public readonly array $meta,
        public readonly int $firstSeenTs,
        public readonly int $identifiedTs,
        public readonly int $lastSeenTs,
        public readonly int $sessionCount,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id: (string) ($row['id'] ?? ''),
            externalId: $row['external_id'] ?? null,
            email: $row['email'] ?? null,
            firstName: $row['first_name'] ?? null,
            lastName: $row['last_name'] ?? null,
            fullName: (string) ($row['full_name'] ?? ''),
            phone: $row['phone'] ?? null,
            company: $row['company'] ?? null,
            jobTitle: $row['job_title'] ?? null,
            website: $row['website'] ?? null,
            country: $row['country'] ?? null,
            city: $row['city'] ?? null,
            lifecycleStage: (string) ($row['lifecycle_stage'] ?? 'user'),
            state: (string) ($row['state'] ?? 'active'),
            meta: array_values((array) ($row['meta'] ?? [])),
            firstSeenTs: (int) ($row['first_seen_ts'] ?? 0),
            identifiedTs: (int) ($row['identified_ts'] ?? 0),
            lastSeenTs: (int) ($row['last_seen_ts'] ?? 0),
            sessionCount: (int) ($row['session_count'] ?? 0),
        );
    }
}
