# Changelog

## 0.1.0 - 2026-08-21

### Added

- **`$client->forms()->all()`** - the property's forms (`GET /v2/property/forms`, `forms:read` scope), as typed `Form` DTOs (`id`, `name`, `state`, `submits`, `lastSubmitAt`, `createdAt`) or raw rows with `asRaw()`. Needs the SnipForm API release that adds the endpoint.
- Raw session rows now carry `is_flagged` on each `form_tags` entry (server side, no SDK change needed to read it). Spam is never tracked onto a visit; a flagged submission is, so check the flag before treating a submission as a real lead. README has a Forms section.

## 0.0.13 — 2026-08-21

The "fire events from the backend" release.

### Added

- **`Snipform::event()` / `eventFor()` / `acquisition()` / `acquisitionFor()` / `revenue()`** on the Laravel facade. Writes resolve the visitor's session id through the full four-tier chain (Laravel session stash from the hydrate loader, then header, form field, query string). `event()` returns `false` instead of throwing when no session id resolves - a JS-disabled visitor is a normal case. The `*For()` variants take a session id you already hold.
- **`SnipFormManager`** - the new facade root. `IdentifyManager` still exists and is still bound (the Login listener + identify middleware type-hint it); the facade now routes through `SnipFormManager`, which composes it and adds events + resources (`signals()`, `session()`, `contacts()`).
- **Dispatch modes** - `sync` | `after_response` | `queue` for every write, via `snipform.events.dispatch` and `snipform.identify.queue`. `queue` is a real `ShouldQueue` job (`SignalWriteJob`) on your queue connection; `after_response` runs in the container's terminating phase (no worker needed). Legacy booleans still work (`true` = after_response). Queue name via `snipform.queue_name`.
- **Tier 1 for `session()->event()` / `acquisition()`**: `Session::fromRequest()` now reads the server-session stash first, so the hydrate flow works for events, not just identify. `Session::stashedSessionId()` exposes tier 1 alone; `Session::SESSION_STASH_KEY` is the single definition of the stash key.
- **`signals()->graph()` and `signals()->live()`** terminals covering the analytics graph + live endpoints, with typed `GraphResult` / `GraphPoint` DTOs (`series`, `previousSeries`, `projectedSeries`).
- **`Contacts::passesDedup()`** - the dedup gate on its own, so callers can defer the HTTP call without deferring the dedup decision.
- **`SnipForm::VERSION`** - drives the `User-Agent` header (was hardcoded to a stale `0.3`).
- **Tests**: `FakeApi` (Guzzle MockHandler-backed client) plus coverage for `HttpClient` error mapping, `Contacts`, the graph terminals, the Laravel dispatcher, the facade root and the sessions page size. 81 unit tests.

### Changed

- `IdentifyManager` constructor gained a `WriteDispatcher` parameter (only the service provider constructs it). Dedup now runs inline (one cache add) while the HTTP call defers - previously both deferred together.
- `HttpClient` accepts an internal `handler` option for tests. Not a public API.

### Fixed

- **`signals()->sessions($perPage)` ignored its page size.** It sent `per_page`, but the sessions endpoint reads `limit` (the one paginated endpoint that does), so every page held the server default of 20 whatever you asked for. It now sends `limit`.
- README documented `session()->resolve()` (deleted in 0.0.10) and a broken end-to-end example built on it. Rewritten around the real flow: hydrate → stash → fire. Contacts finally has its own section.

## 0.0.12 — 2026-05-31

- Identify dedup TTL default and config wording.

## 0.0.11 — 2026-05-31

### Added

- **Contacts resource** (`$client->contacts()`): `identify`, `identifyOnce` (cache-gated dedup), `identifyFromRequest`, `find`, `all` (paginated), `sessionsFor` (cursor-walking generator), `update`, `delete`. Typed `Contact` DTO.
- **Laravel identify layer**: `Identifiable` trait + contract for User models (auto-derives the payload), `IdentifyOnLogin` listener on `Auth\Events\Login`, `snipform.identify` middleware for token/SPA auth, `IdentifyManager` + `Snipform` facade, `config/snipform.php` (publishable, `--tag=snipform-config`) with `identify.{on_login,queue,dedup_ttl,cache_store}`.
- `Client::withIdentifyDedup()` - plug any atomic add-if-missing cache primitive in as the dedup gate.

## 0.0.10 — 2026-05-29

### Added

- **Attribution resource**: `attribution()->preview()` (classify a URL + referrer into channel/source/medium) and `presets()`. Typed `ChannelResult` DTO.
- **`SnipFormSessionMiddleware`** + `SnipFormServiceProvider` (auto-discovered): lifts the visitor's session id off header / form / query into `$request->attributes`.

### Removed

- **`session()->resolve()`** and the `ResolveResult` DTO. Server-side fingerprint resolution depended on the customer's IP-detection chain being trustworthy; it isn't, reliably. Session ids are browser-canonical - use the transports (`attachToFetch` / `attachToForm`) or, in Laravel, the hydrate loader.

## 0.0.9 — 2026-05-28

### Added

- **`SessionField` enum** with declared `FieldType`s - typed ids for every queryable session field, with IDE autocomplete on `where()`.
- **`IncompatibleFieldOperator`** exception: numeric/keyword operator guards fire SDK-side before any HTTP.
- **Explicit pagination** on `PaginatedCollection`: `page()`, `count()`, `first()`, `all()`; typed `Page` DTO.

## 0.0.8 — 2026-05-28

- Builders, `ConversionAnalytics`, and `Clicks` implement `JsonSerializable` so they can be returned straight from a Laravel controller.

## 0.0.7 — 2026-05-28

### Changed

- **`asRaw()`** (the `RawAware` concern) replaces per-DTO `raw()` payload carrying. DTOs are pure typed value objects; flip the resource into raw mode to get the API array verbatim. `SnipFormDTO::raw()` removed.

## 0.0.6 — 2026-05-28

- **Custom periods**: `between($from, $to)`, `customPeriod()`, `fromDate()`, `toDate()` on the builder.

## 0.0.5 — 2026-05-28

- **Sensible error responses**: `ApiException` surfaces field-level validation errors in its message and exposes `status` / `errors` / `body`; 401/403 map to `AuthenticationException`.

## 0.0.4 — 2026-05-28

- DTOs moved from `Resources/` to `Data/` (namespace `SnipForm\Data\*`). `Signals::query()` deprecated - the resource is itself a builder.

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
