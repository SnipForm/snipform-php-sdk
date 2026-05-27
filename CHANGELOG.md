# Changelog

## 0.0.2 — 2026-05-27

### Added

- **`Client::properties()`** resource backing the `GET /property/overview` endpoint, with a typed **`PropertyOverview`** value object (`id`, `name`, `domain`, `hasSignals`, `state`, `stateName`, `counts`, `raw()`).
- **`path_prefix`** client option (default `/v2/`) so the URL path between `base_url` and the resource can be overridden per deployment.
- **`verify_ssl`** client option (default `true`) — set `false` for local self-signed certificates.

### Changed

- **Breaking:** default HTTP path prefix is `/v2/` (was `/api/v2/`). This matches the live SnipForm API. If your deployment serves the API under `/api/v2/`, pass `'path_prefix' => '/api/v2/'` when constructing the client.
- **`MetricsResult::fromResponse()`** now reads from the correct response path (`analytics.period_metrics.summary.{metric}.current`) so the typed fields actually populate. Previously every field was 0 against the real API. `bounceRate` is normalised to 0-100 (matching the README's documented range) by multiplying the API's 0-1 value.

## 0.0.1

- Initial release.
