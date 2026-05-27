# Changelog

## 0.0.3 — 2026-05-27

### Added

- **`Period` enum** in `SnipForm\Query\Period` — typed cases for every named period (`TODAY`, `YESTERDAY`, `LAST_7`, `LAST_28`, `MONTH_TO_DATE`, `YEAR_TO_DATE`, `LAST_12_MONTHS`, `CUSTOM`). `period()` accepts a case or a string and validates upfront.
- **`InvalidPeriodException`** — thrown SDK-side when `period('foo')` is called with an unknown value. Carries the list of allowed values. Previous behaviour was a 500 from the server.
- **Typed period shorthands on `Signals\Builder`**: `today()`, `yesterday()`, `last7Days()`, `last28Days()`, `monthToDate()`, `yearToDate()`, `last12Months()`. Pure IDE autocomplete — no magic strings.

### Changed

- **Breaking (wire format):** signals queries now post **structured clauses** under the `clauses` key instead of serialized URL-DSL strings under `query`. Each clause is `{id, op, value, where?, not?}`. Field/subfield/type are resolved server-side from the public `id` via `SignalFieldMappingSet`, so the wire payload stays small. The fluent surface on `Builder` is unchanged — only `buildPayload()` shape and the over-the-wire body differ.
- **Removed:** `Builder::raw(...)` and `Query\ClauseSerializer`. Nested-tag clauses now go through normal `where()` calls against their public ids (e.g. `where('tags_fbclid', '...')`).
- **Server-side period validation**: invalid periods now return a 422 with the allowed list, not a 500.

## 0.0.2 — 2026-05-27

### Added

- **`SnipFormDTO`** abstract base class for every typed return object. Provides `raw()` (full payload or `raw('key')` for a single field) and `toArray()` (public fields, raw excluded). Every DTO in `Resources/` now extends it.
- **`Client::properties()`** resource backing the `GET /property/overview` endpoint, with a typed **`PropertyOverview`** value object (`id`, `name`, `domain`, `hasSignals`, `state`, `stateName`, `counts`).
- **`path_prefix`** client option (default `/v2/`) so the URL path between `base_url` and the resource can be overridden per deployment.
- **`verify_ssl`** client option (default `true`) — set `false` for local self-signed certificates.

### Changed

- **Breaking:** default HTTP path prefix is `/v2/` (was `/api/v2/`). This matches the live SnipForm API. If your deployment serves the API under `/api/v2/`, pass `'path_prefix' => '/api/v2/'` when constructing the client.
- **`MetricsResult::fromResponse()`** now reads from the correct response path (`analytics.period_metrics.summary.{metric}.current`) so the typed fields actually populate. Previously every field was 0 against the real API. `bounceRate` is normalised to 0-100 (matching the README's documented range) by multiplying the API's 0-1 value.

## 0.0.1

- Initial release.
