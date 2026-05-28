# SnipForm PHP SDK

Official PHP SDK for the [SnipForm](https://snipform.io) API. Eloquent-flavoured query builder over the V2 endpoints.

```bash
composer require snipform/php-sdk
```

## Quick start

```php
use SnipForm\SnipForm;

$snipform = SnipForm::client('snipform_pat_xxx');

// List sessions matching a query — auto-paginated
foreach ($snipform->signals()
    ->last28Days()
    ->where('country', 'US')
    ->whereStartsWith('entry_path', '/blog')
    ->sessions() as $session
) {
    echo $session->entryPath.' from '.$session->source.PHP_EOL;
}

// Headline metrics for the same query
$metrics = $snipform->signals()
    ->last7Days()
    ->where('utm_content', 'pub_12345')   // an affiliate
    ->metrics();

echo "Sessions: {$metrics->sessions}, bounce: {$metrics->bounceRate}%";
```

## Contents

- [Data Objects (DTOs)](#data-objects-dtos)
- [Returning from a Laravel controller](#returning-from-a-laravel-controller)
- [Property](#property)
- [Signals query builder](#signals-query-builder)
  - [Periods](#periods)
  - [Sessions](#sessions-lazy)
  - [Metrics](#metrics)
- [Short links](#short-links)
- [Session actions](#session-actions)
- [Conversions](#conversions)
- [`asRaw()` — opt-out of typed objects](#asraw--opt-out-of-typed-objects)
- [Authentication](#authentication)
- [Error handling](#error-handling)
- [Configuration](#configuration)
- [Tests](#tests)

## Data Objects (DTOs)

Every typed return object extends `SnipForm\Data\SnipFormDTO` — a pure typed value object with public `readonly` fields. Two helpers:

```php
$dto->toArray();   // public fields as an associative array
json_encode($dto); // implements JsonSerializable — same as toArray()
```

DTOs hold no original payload; if you need the raw API JSON, flip the resource into raw mode with [`asRaw()`](#asraw--opt-out-of-typed-objects).

## Returning from a Laravel controller

DTOs and `PaginatedCollection` both implement `JsonSerializable`, so a Laravel (or any PSR-7) controller can return them directly:

```php
public function dashboard(SnipForm $snipform)
{
    return $snipform->signals()->last7Days()->metrics();    // serializes to JSON
}

public function sessions(SnipForm $snipform)
{
    return $snipform->signals()->last7Days()->sessions();   // page 1 of Laravel paginator JSON
}

public function property(SnipForm $snipform)
{
    return $snipform->properties()->overview();             // PropertyOverview → typed JSON
}
```

A `PaginatedCollection` serializes as Laravel's standard pagination JSON (`data`, `current_page`, `last_page`, `total`, `next_page_url`, …). Only page 1 is serialized — iterate first if you want all pages.

## Property

The token is scoped to a single SnipForm Property. Pull its identity + headline counts:

```php
$property = $snipform->properties()->overview();

$property->id;             // string
$property->name;           // string
$property->domain;         // string
$property->hasSignals;     // bool — tracking has fired at least once
$property->state;          // string|null — raw state value
$property->stateName;      // string|null — human label
$property->counts;         // array — e.g. ['sessions' => 188862, 'forms' => 4, 'pages' => 2]
```

## Signals query builder

The first argument to every `where*()` method is a public field **id** (the same ones in `SignalFieldMappingSet`). Field/subfield/type are resolved server-side from the id, so the wire stays small.

| Method | Op | Use for |
|---|---|---|
| `where($id, $value)` | `equals` | equality (array value = IN) |
| `orWhere(...)` | `equals` (where=or) | OR clause |
| `whereNot(...)` | `equals` (not=true) | negate |
| `orWhereNot(...)` | `equals` (where=or, not=true) | OR negate |
| `whereStartsWith($id, $v)` | `starts_with` | prefix |
| `whereContains($id, $v)` | `contains` | substring |
| `whereRegex($id, $pat)` | `regex` | regex |
| `whereGt / Gte / Lt / Lte` | `gt / gte / lt / lte` | numeric comparison |
| `whereBetween($id, $a, $b)` | `between` | numeric range |
| `whereExists($id)` | `exists` | field is present |
| `whereNotExists($id)` | `exists` (not=true) | field is absent |

Each clause posts as `{id, op, value, where?, not?}`. `where` and `not` are omitted at default values.

### Periods

Use the typed shorthands for autocomplete, or pass a `Period` case to `period()`.

```php
->today()
->yesterday()
->last7Days()
->last28Days()
->monthToDate()
->yearToDate()
->last12Months()

// Custom date ranges — pick whichever form reads best
->between('2026-01-01', '2026-01-31')      // both at once
->customPeriod('2026-01-01', '2026-01-31') // same
->customPeriod()
    ->fromDate('2026-01-01')
    ->toDate('2026-01-31')                  // piecemeal

// Or via the enum:
use SnipForm\Query\Period;
->period(Period::LAST_28)
->period('last_28')                         // string also fine; validated upfront
```

Invalid period strings throw `SnipForm\Exceptions\InvalidPeriodException` immediately — no HTTP round-trip.

### Sessions, lazy

`->sessions()` returns a `PaginatedCollection` you can iterate. Each iteration step pulls the next page transparently.

```php
foreach ($snipform->signals()->where('device', 'mobile')->sessions() as $session) { ... }
$first  = $snipform->signals()->where('device', 'mobile')->sessions()->first();
$total  = $snipform->signals()->where('device', 'mobile')->sessions()->count();
$page2  = $snipform->signals()->where('device', 'mobile')->sessions()->page(2);
$all    = $snipform->signals()->where('device', 'mobile')->sessions()->all(); // careful
```

### Metrics

Returns a `MetricsResult` value object:

```php
$m = $snipform->signals()->last28Days()->metrics();
$m->sessions;          // int
$m->views;             // int
$m->viewsPerSession;   // float
$m->bounceRate;        // float (0-100)
$m->duration;          // int (seconds)
$m->avgScroll;         // float (0-100)
$m->showing;           // human-readable date span
$m->tookMs;            // server query time
```

To reach trend data (previous-period, percent, difference), use [`asRaw()`](#asraw--opt-out-of-typed-objects).

## Short links

Three resources: groups (folders), links (the short URLs themselves), and clicks (the redirect events). Scoped to the property your token belongs to.

### Link groups

```php
$groups   = $client->linkGroups()->all();                        // LinkGroup[]
$group    = $client->linkGroups()->find($id);                    // LinkGroup
$group    = $client->linkGroups()->create([
    'name' => 'Spring affiliates',
    'description' => 'Affiliate links for Q2',
    'purpose' => 'affiliate',
    'track_clicks' => true,
]);
$group    = $client->linkGroups()->update($id, ['name' => 'Spring 2026']);
$deleted  = $client->linkGroups()->delete($id);                  // bool, cascades the group's links
```

### Links

```php
// Paginated list — auto-walks every page
foreach ($client->links()->all() as $link) {
    echo $link->shortUrl.' → '.$link->destinationUrl.PHP_EOL;
}

// Filter by group
foreach ($client->links()->all(['group_id' => $groupId]) as $link) { ... }

$link = $client->links()->find($id);                             // Link
$link = $client->links()->create([
    'group_id' => $groupId,
    'destination_url' => 'https://example.com/landing',
    'domain' => 'snpf.io',
    'utm' => [
        'utm_source' => 'ofillio',
        'utm_medium' => 'affiliate',
        'utm_campaign' => 'spring_sale',
        'utm_content' => 'pub_12345',     // individual affiliate
    ],
]);
$link = $client->links()->update($id, [
    'destination_url' => 'https://example.com/new-landing',
    'is_active' => false,
]);
$client->links()->delete($id);
```

Each link exposes a small accessor for utm values:

```php
$link->utm('utm_content');  // 'pub_12345' or null
```

### Clicks

Read-only — clicks are recorded server-side from short-link redirects. The fluent filter builder chains until you call `->all()` or `->find()`:

```php
// Every click for one link, walking pages
foreach ($client->clicks()->forLink($linkId)->all() as $click) {
    echo $click->city.' on '.$click->device.PHP_EOL;
}

// Last 30 days of human clicks on a whole campaign
foreach ($client->clicks()
    ->forGroup($groupId)
    ->between(strtotime('-30 days'), time())
    ->usersOnly()
    ->all() as $click
) { ... }

// Just bot traffic
$bots = $client->clicks()->botsOnly()->all()->count();

// Single click
$click = $client->clicks()->find($clickId);
```

Filters:

| Method | Effect |
|---|---|
| `forLink($id)` | scope to one short link |
| `forGroup($id)` | scope to a link group |
| `between($fromTs, $toTs)` | unix timestamp range |
| `since($fromTs)` | open-ended range |
| `usersOnly()` | exclude bot clicks |
| `botsOnly()` | only bot clicks |
| `perPage($n)` | page size, 1–100 |

## Session actions

Three writes scoped to a single SignalSession: resolve a visitor's session id from their request, submit a custom event, and patch acquisition metadata.

### Resolve

Looks up the SignalSession that belongs to a visitor — by hashing their IP + User-Agent + language with the same daily salt the JS tracker uses. The visitor must already have been tracked once today on this property for the lookup to find a match.

The SDK accepts a Symfony or Laravel Request and pulls those values for you. Pass `$request` from your controller:

```php
// Laravel
public function handleVisitor(Request $request)
{
    $resolved = $snipform->session()->resolve($request);
    // → ResolveResult { resolved: true, sessionId: 'abc...', sid: 'hash...' }
}
```

```php
// Symfony
public function handle(Request $request): Response
{
    $resolved = $snipform->session()->resolve($request);
}
```

Or pass values explicitly if you're not on a Symfony-flavoured framework:

```php
$resolved = $snipform->session()->resolve([
    'ip'         => $myFramework->getClientIp(),
    'user_agent' => $myFramework->getUserAgent(),
    'lang'       => $myFramework->getAcceptLanguage(),
]);
```

`$resolved->resolved` is `false` if the visitor hasn't been tracked yet today on this property. Handle that case before chaining further writes.

> **Important:** the `ip` must be the **visitor's** IP from your incoming request, not your server's outbound IP. Your framework's `$request->getClientIp()` / equivalent does the right thing automatically (resolves through proxies / CDNs). The SDK does not inspect the transport-level IP of its own outbound call.

### Event

Submit a custom event for a session. Identifies the target session in one of two ways:

```php
// Explicit session_id in the payload
$event = $snipform->session()->event([
    'session_id' => $resolved->sessionId,
    'name'       => 'purchase',
    'value'      => 99.99,            // optional
    'meta'       => ['order_id' => 'X-1', 'currency' => 'USD'],  // optional
]);

// Or pass the request — SDK reads session_id from the X-SnipForm-Session-Id
// header or `snip_session_id` body field (set by signals.js attachToFetch /
// attachToForm on the customer's page)
$event = $snipform->session()->event($request, [
    'name'  => 'purchase',
    'value' => 99.99,
]);
```

Returns a typed `Event` value object.

### Acquisition

Patch acquisition metadata onto a session. Partial — only supplied keys are written. Tags merge with existing tags (deduped); cost / value / currency overwrite.

```php
$snipform->session()->acquisition([
    'session_id'    => $resolved->sessionId,
    'cost'          => 250,          // optional, integer
    'value'         => 9900,         // optional, integer
    'currency_code' => 'USD',        // optional, ISO 4217
    'tags'          => ['affiliate'],// optional, merged
]);

// Or via Request, same as event()
$snipform->session()->acquisition($request, [
    'value' => 9900,
    'tags'  => ['paid'],
]);
```

Returns the resulting `acquisition_meta` array along with the session id.

### Typical end-to-end flow

```php
public function recordConversion(Request $request)
{
    $resolved = $snipform->session()->resolve($request);
    if (! $resolved->resolved) {
        return;  // visitor hasn't been tracked yet
    }

    $snipform->session()->event([
        'session_id' => $resolved->sessionId,
        'name'       => 'purchase',
        'value'      => $order->total,
    ]);

    $snipform->session()->acquisition([
        'session_id'    => $resolved->sessionId,
        'value'         => (int) ($order->total * 100),
        'currency_code' => $order->currency,
        'tags'          => ['paid'],
    ]);
}
```

## Conversions

Two surfaces:

- **Definition CRUD** — list / find / create / update / replaceSteps / publish / toggle / delete, plus a `schema()` lookup that returns the catalog of valid trigger types.
- **Analytics reads** — `->for($id)` opens a fluent reader: summary, segments, cycles, sessions-at-step.

### Schema

Inspect the catalog of trigger types, conversion types, segment dimensions, and valid match modes before building a config:

```php
$schema = $snipform->conversions()->schema();
// $schema['conversion_types']     — ['lead', 'sale', 'signup', 'activation', 'download', 'custom']
// $schema['trigger_types']        — full details per type (kind, defaults, fieldOptions, matchOptions, …)
// $schema['cycle_intervals']      — ['day', 'week', 'month']
// $schema['segment_dimensions']   — list of segmentable fields
// $schema['page_match_modes']     — ['contains', 'exact', 'starts_with', 'regex']
// $schema['event_value_match_modes'] — ['exists', 'equals', 'gt', 'gte', 'lt', 'lte']
```

### Definition CRUD

```php
$all = $snipform->conversions()->all();                  // Conversion[]
$c   = $snipform->conversions()->find($id);              // Conversion (with .steps populated)
```

Create a conversion via the fluent builder — each `step()` opens a sub-builder whose `->on*()` method commits the step and returns the parent for chaining:

```php
$conversion = $snipform->conversions()->create()
    ->name('Newsletter signup')
    ->description('Free trial sign-up flow')
    ->type('lead')
    ->conversionValue(5.00)
    ->defaultPeriod('last_28')
    ->defaultCycle('week')

    ->step('Visit pricing')->onPageView('/pricing')
    ->step('Click signup')->onEvent('signup_click')
    ->step('Submit form')->onFormSubmit($snipFormId)

    ->publish()           // optional — leaves it in 'draft' otherwise
    ->save();             // → Conversion
```

Step trigger terminals:

| Method | Triggers when |
|---|---|
| `->onPageView($value, $match = 'contains', $field = 'path')` | Visitor reaches a page |
| `->onEntryPage($value, $match = 'contains', $field = 'entry_path')` | Session entered on a page |
| `->onEvent($name, $value = null, $valueMatch = 'exists')` | Custom event fires |
| `->onFormSubmit($snipFormId)` | A specific SnipForm submits |
| `->onShortLink($id, $scope = 'link')` | Session arrived via short link/group |

Each step builder also exposes `->optional()` to mark the step `is_required: false`.

Patch existing definitions:

```php
$snipform->conversions()->update($id, [
    'name'             => 'Renamed',
    'conversion_value' => 12.5,
]);

// Replace the full steps list atomically
$snipform->conversions()->replaceSteps($id, [
    ['name' => 'Visit', 'trigger_type' => 'page_view',   'trigger_config' => ['type' => 'page',  'field' => 'path', 'match' => 'contains', 'value' => '/pricing']],
    ['name' => 'Buy',   'trigger_type' => 'event',       'trigger_config' => ['type' => 'event', 'name' => 'purchase', 'valueMatch' => 'exists']],
]);

$snipform->conversions()->publish($id);   // draft → active
$snipform->conversions()->toggle($id);    // active <-> paused
$snipform->conversions()->delete($id);    // bool
```

### Analytics

`->for($id)` returns a `ConversionAnalytics` you chain a window onto, then call a terminal:

```php
$reader = $snipform->conversions()->for($id)
    ->between(strtotime('-30 days'), time())
    ->filter(['channel_category' => 'paid_search']);   // optional

$summary = $reader->summary();
$summary->sessions;       // int
$summary->conversions;    // int
$summary->rate;           // float (0-100)
$summary->value;          // float|null — total attributed value
$summary->funnel;         // FunnelStep[] — per-step counts + drop_off
```

Slice by a flat dimension or a custom tag key:

```php
$reader->segments('channel_category');      // ConversionSegment[]
$reader->segmentsByTag('campaign_phase');   // ConversionSegment[]
```

Cycle through day/week/month buckets with deltas vs the prior bucket:

```php
$cycles = $reader->cycles('week', page: 0, perPage: 6);
// → ['cycles' => ConversionCycle[], 'has_more' => bool, 'page' => int, 'interval' => 'week']

foreach ($cycles['cycles'] as $c) {
    echo "{$c->label}: {$c->conversions}/{$c->sessions} = {$c->rate}% (Δ{$c->delta}%)\n";
}
```

Drill into sessions that reached a specific funnel step:

```php
$step = $reader->sessionsAt($stepId, page: 1, perPage: 25);
// → ['sessions' => array[], 'page' => int, 'per_page' => int, 'total' => int, 'has_more' => bool]
```

Window setters: `->between($fromTs, $toTs)` or `->since($fromTs)` (open-ended to now). Both take unix timestamps.

## `asRaw()` — opt-out of typed objects

Every resource (and every builder chain) supports `->asRaw()`. Terminals return the underlying API array instead of hydrating a typed DTO. Useful when you want fields the SDK doesn't surface, or when you're forwarding API responses to a frontend that already expects the SnipForm JSON shape.

```php
$client->properties()->overview();              // PropertyOverview
$client->properties()->asRaw()->overview();     // array

$client->signals()->last28Days()->metrics();              // MetricsResult
$client->signals()->last28Days()->asRaw()->metrics();     // array — analytics, meta, options

$client->signals()->last28Days()->sessions();             // PaginatedCollection<SessionRow>
$client->signals()->last28Days()->asRaw()->sessions();    // PaginatedCollection<array>

$client->linkGroups()->find($id);                         // LinkGroup
$client->linkGroups()->asRaw()->find($id);                // array

$client->conversions()->find($id);                        // Conversion
$client->conversions()->asRaw()->find($id);               // array

$client->conversions()->asRaw()->create()                 // ConversionBuilder (raw flag forwarded)
    ->name(...)->step('x')->onPageView(...)
    ->save();                                             // array

$client->conversions()->asRaw()->for($id)                 // ConversionAnalytics (raw flag forwarded)
    ->since(strtotime('-30 days'))
    ->summary();                                          // array
```

Each `$client->resource()` call returns a fresh instance, so flipping `asRaw` on one chain doesn't affect the next.

## Authentication

The SDK takes a property-scoped Personal Access Token. Generate one in **Property → Settings → API Tokens**. Tokens carry scope (e.g. `signals:read`, `conversions:write`) — the SDK forwards them and the API enforces.

## Error handling

```php
use SnipForm\Exceptions\AuthenticationException;
use SnipForm\Exceptions\ApiException;
use SnipForm\Exceptions\InvalidPeriodException;
use SnipForm\Exceptions\MissingSessionIdException;
use SnipForm\Exceptions\SnipFormException;

try {
    $sessions = $snipform->signals()->last7Days()->sessions()->all();
} catch (InvalidPeriodException $e) {
    // SDK-side — invalid string passed to period(). Caught before any HTTP call.
} catch (MissingSessionIdException $e) {
    // SDK-side — session_id couldn't be resolved from the Request.
} catch (AuthenticationException $e) {
    // 401 / 403 — token bad or out of scope.
} catch (ApiException $e) {
    // 4xx / 5xx with a structured body — see $e->status, $e->errors, $e->body.
    // Validation errors are expanded into the message inline:
    //   "The given data was invalid. — period: must be one of ..."
} catch (SnipFormException $e) {
    // any other SDK-side failure (transport, JSON decode, etc.)
}
```

`ApiException` exposes:

| Property | Type | Notes |
|---|---|---|
| `->status` | int | HTTP status code |
| `->errors` | array | Laravel-style `['field' => ['msg', ...]]` — empty for non-validation errors |
| `->body` | array | Full unwrapped response body |

## Configuration

```php
SnipForm::client('snipform_pat_xxx', [
    'base_url'    => 'https://api.snipform.io',  // default
    'path_prefix' => '/v2/',                      // default; older deployments may serve under '/api/v2/'
    'timeout'     => 30,                          // seconds, request timeout
    'verify_ssl'  => true,                        // default; set false for local self-signed certs
]);
```

## Tests

The unit suite is hermetic — no network, no env config required:

```bash
composer install
vendor/bin/phpunit --testsuite=Unit
```

There's also a `tests/Integration` suite that hits a live SnipForm deployment. Copy the env template, fill in a token + base URL, then:

```bash
cp tests/.env.testing.example tests/.env.testing
# edit tests/.env.testing — set SNIPFORM_TEST_TOKEN
vendor/bin/phpunit
```

Integration tests are skipped when `SNIPFORM_TEST_TOKEN` isn't set, so CI without secrets still runs unit-only.
