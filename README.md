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

In Laravel, writes are one line - the SDK resolves the visitor's session id for you:

```php
use SnipForm\Laravel\Facades\Snipform;

Snipform::event('purchase', 9900, ['order' => $order->id]);   // custom event on the current visitor
Snipform::revenue(9900, 'USD');                                // acquisition value, in cents
Snipform::identify($user);                                     // contact, from your User model
```

## Contents

- [Data Objects (DTOs)](#data-objects-dtos)
- [Returning from a Laravel controller](#returning-from-a-laravel-controller)
- [Property](#property)
- [Signals query builder](#signals-query-builder)
  - [Periods](#periods)
  - [Sessions](#sessions-lazy)
  - [Pages](#pages--explicit-pagination)
  - [Metrics](#metrics)
  - [Graph + live](#graph--live)
- [Short links](#short-links)
- [Session actions](#session-actions)
- [Contacts](#contacts)
- [Conversions](#conversions)
- [Attribution](#attribution)
- [`asRaw()` — opt-out of typed objects](#asraw--opt-out-of-typed-objects)
- [Laravel](#laravel)
  - [Reading the visitor's session id](#reading-the-visitors-session-id)
  - [Firing events from Laravel](#firing-events-from-laravel)
  - [Auto-identify on login](#auto-identify-on-login)
  - [`Snipform` facade](#snipform-facade)
  - [Identify middleware (SPAs / token APIs)](#identify-middleware-spas--token-apis)
  - [Manual setup — `Client` injection](#manual-setup--client-injection)
  - [`SnipFormSessionMiddleware`](#snipformsessionmiddleware)
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

The first argument to every `where*()` method is a public field id. Pass it as a `SessionField` enum case (IDE-discoverable, type-checked) or as a bare string (escape hatch, no SDK-side validation).

```php
use SnipForm\Query\SessionField;

$snipform->signals()
    ->where(SessionField::COUNTRY, 'US')
    ->whereBetween(SessionField::TIME_ON_SITE, 60, 300)
    ->whereStartsWith(SessionField::ENTRY_PATH, '/blog')
    ->sessions();

// Strings still work — the SDK doesn't know the field's type without an
// enum case, so op/field mismatches won't be caught client-side:
$snipform->signals()->where('country', 'US')->sessions();
```

Field/subfield/type are resolved server-side from the id, so the wire stays small.

**Type-safe operators**: when you pass an enum case, the SDK validates the operator against the field's type and throws `IncompatibleFieldOperator` *before* HTTP:

```php
$snipform->signals()->whereBetween(SessionField::COUNTRY, 0, 10);
// → IncompatibleFieldOperator: Operator `between` is not valid for field
//   `country` (type: keyword). Valid ops: equals, contains, starts_with,
//   regex, exists.
```

`SessionField` cases are grouped by concern: entry/exit page, referrer, tags, geo, browser/device/OS, bot detection, channel + UTM attribution, acquisition value, short links, forms, events, session metrics. Bare string fallback covers anything new the server adds before the enum catches up.


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
$all    = $snipform->signals()->where('device', 'mobile')->sessions()->all(); // careful
```

### Pages — explicit pagination

`->page($n)` returns a `Data\Page` object that carries the page's items *plus* the full Laravel paginator meta and navigation methods. One HTTP call gives you both — no separate `->count()`.

```php
$page = $snipform->signals()->sessions(20)->page(2);

$page->items;          // SessionRow[]   (or array[] in asRaw)
$page->currentPage;    // 2
$page->lastPage;       // 5
$page->total;          // 230
$page->perPage;        // 20
$page->from;           // 21
$page->to;             // 40
$page->nextPageUrl;    // string|null
$page->prevPageUrl;    // string|null
$page->hasMore();      // bool
$page->isFirstPage();
$page->isLastPage();

// Navigation — each is one HTTP call, returns the related Page
$next  = $page->next();    // → Page 3, or null when on last page
$prev  = $page->prev();    // → Page 1, or null when on first page
$first = $page->first();
$last  = $page->last();

// Jump by URL — pass any of the paginator URLs (or a link from the
// Laravel-style `links` array) and the SDK parses the `page` query param.
$jump = $page->pageLink($page->nextPageUrl);
$jump = $page->pageLink('https://api.snipform.io/v2/.../sessions?page=7');

// Render numbered page links from Laravel's `links` collection
foreach ($page->raw()['links'] ?? [] as $link) {
    if ($link['url']) {
        $other = $page->pageLink($link['url']);
    }
}
```

`Page` is iterable, countable, and array-accessible — so existing `foreach`/`count`/`$page[0]` usage keeps working:

```php
foreach ($snipform->signals()->sessions()->page(2) as $session) { ... }
$rowsOnThisPage = count($page);   // count of items on THIS page (not total)
$first = $page[0];
```

Returning a `Page` from a Laravel controller serializes it as the standard Laravel paginator JSON for that page:

```php
public function sessions(SnipForm $snipform, Request $request)
{
    return $snipform->signals()
        ->last28Days()
        ->sessions(20)
        ->page((int) $request->input('page', 1));
}
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

### Graph + live

Time-series for one metric over the chain's period, bucketed by interval. Returns a `GraphResult`:

```php
$g = $snipform->signals()->last28Days()->where('country', 'US')->graph('sessions');
//                                                            ->graph('bounce', 'day', compare: true)

$g->metric;            // 'sessions'
$g->metricLabel;       // 'Sessions'
$g->series;            // GraphPoint[] — current period, one per bucket: ->label, ->value
$g->previousSeries;    // GraphPoint[] — filled when compare: true
$g->projectedSeries;   // GraphPoint[] — dotted tail projecting the still-running bucket
```

Metrics: `sessions`, `views`, `views_session`, `bounce`, `duration`, `scroll`. Interval defaults to whatever fits the period (`hour` / `day` / `week` / `month`).

`live()` is the same shape for the live window - period settings on the chain are ignored, clauses still apply:

```php
$now = $snipform->signals()->where('device', 'mobile')->live();   // or ->live('views')
```

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

Two writes scoped to a single SignalSession: submit a custom event, and patch acquisition metadata. Both need the visitor's **session id** - and that only ever originates in the browser, where the JS tracker resolves it. There is no server-side lookup by IP or user agent (it existed once; it depended on your proxy chain reporting the right IP, which it can't be trusted to do).

So the id has to travel from the page to your backend. The SDK resolves it from a `Request` through four transports, in priority order:

1. **Server session** - stashed by the Laravel [hydrate loader](#reading-the-visitors-session-id) on a prior request. Survives redirects, form posts, and AJAX alike.
2. **`X-SnipForm-Session-Id` header** - attached by the tracker's `signals.attachToFetch()` on same-origin AJAX.
3. **`snip_session_id` form field** - injected by `signals.attachToForm()` on a classic form post.
4. **`snip_session_id` query string** - appended by hand.

Or pass `session_id` explicitly - for example the id you stored against an order at checkout, so you can fire `order_shipped` from a queued job days later.

> **Laravel:** skip straight to [`Snipform::event()`](#firing-events-from-laravel) - it resolves the id, picks the dispatch mode, and never throws for a visitor the tracker couldn't see.

### Event

```php
// Pass the request — the SDK resolves session_id through the four transports
$event = $snipform->session()->event($request, [
    'name'  => 'purchase',
    'value' => 9900,                                      // optional
    'meta'  => ['order_id' => 'X-1', 'currency' => 'USD'], // optional
]);

// Or supply an explicit session_id (one you stored earlier)
$event = $snipform->session()->event([
    'session_id' => $order->snipform_session_id,
    'name'       => 'order_shipped',
]);
```

Returns a typed `Event` value object. Throws `MissingSessionIdException` when a `Request` carries no resolvable id - call `$snipform->session()->fromRequest($request)` first if you'd rather branch than catch.

### Acquisition

Patch acquisition metadata onto a session. Partial — only supplied keys are written. Tags merge with existing tags (deduped); cost / value / currency overwrite. **Money is in integer cents.**

```php
$snipform->session()->acquisition($request, [
    'value'         => 9900,         // optional, integer cents
    'cost'          => 250,          // optional, integer cents
    'currency_code' => 'USD',        // optional, ISO 4217
    'tags'          => ['affiliate'],// optional, merged
]);

// Or with an explicit session_id, same as event()
$snipform->session()->acquisition(['session_id' => $sessionId, 'value' => 9900]);
```

Returns the session id, the resulting `acquisition_meta`, and the value block.

### Typical end-to-end flow

Capture the id at the moment you have it; store it; fire later writes against the stored id.

```php
// Checkout controller — the visitor is on the page, so the id resolves
public function store(Request $request, \SnipForm\Client $snipform)
{
    $order = Order::create([
        // ...
        'snipform_session_id' => $snipform->session()->fromRequest($request),   // may be null
    ]);

    if ($order->snipform_session_id) {
        $snipform->session()->event($request, ['name' => 'purchase', 'value' => $order->total_cents]);
        $snipform->session()->acquisition($request, ['value' => $order->total_cents, 'currency_code' => $order->currency]);
    }
}

// Fulfilment job, days later — no request, just the stored id
public function handle(\SnipForm\Client $snipform)
{
    if ($this->order->snipform_session_id) {
        $snipform->session()->event(['session_id' => $this->order->snipform_session_id, 'name' => 'order_shipped']);
    }
}
```

## Contacts

Bridge anonymous sessions to known people. A `Contact` holds the PII (email, name, phone, company, custom traits); sessions reference it by id only.

```php
// Find-or-create by external_id (preferred) or email, set-once link to the current session
$contact = $snipform->contacts()->identify([
    'external_id' => 'usr_123',
    'email'       => 'jane@acme.com',
    'session_id'  => $sessionId,                       // optional — link happens only when present
    'traits'      => [
        'first_name' => 'Jane',
        'company'    => 'Acme',
        'lifecycle_stage' => 'customer',
        'meta'       => [['key' => 'plan', 'value' => 'pro']],
    ],
]);

// Same, with a client-side dedup gate so repeat calls don't hit the API
$fired = $snipform->contacts()->identifyOnce([...]);          // bool — see Client::withIdentifyDedup()
$fired = $snipform->contacts()->identifyFromRequest($request, [...]);   // merges the tracker header

// Read
$contact = $snipform->contacts()->find($id);
foreach ($snipform->contacts()->all(['search' => 'acme']) as $c) { ... }   // auto-paginated
foreach ($snipform->contacts()->sessionsFor($id) as $session) { ... }    // cursor-walks every page

// Update
$snipform->contacts()->update($id, ['lifecycle_stage' => 'customer']);

// Delete — redaction, not a row drop: every identifying field is destroyed,
// related identify events are scrubbed, and the contact's sessions keep only
// an anonymous reference. This is how an erasure request is honoured.
$snipform->contacts()->delete($id);
```

The `Contact` DTO exposes `id`, `externalId`, `email`, `firstName`, `lastName`, `fullName`, `phone`, `company`, `jobTitle`, `website`, `country`, `city`, `lifecycleStage`, `state`, `meta`, `firstSeenTs`, `identifiedTs`, `lastSeenTs`, `sessionCount`.

Identify is idempotent: resolution is by `external_id` first, else `email`; a session is linked once and never re-linked. Browsers sending Global Privacy Control are never identified through the public tracker endpoint - server-side identify (this SDK) carries no browser signal and is unaffected.

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

## Attribution

`$client->attribution()` exposes the SnipForm channel-attribution engine for diagnostics — useful for "Test attribution" buttons in a link builder UI, or for verifying that a campaign's UTM combo will land in the channel category you expect.

```php
// By explicit UTM keys
$result = $client->attribution()->preview([
    'utm_source'   => 'whatsapp',
    'utm_medium'   => 'social',
    'utm_campaign' => 'spring',
]);

// Or by full URL — SDK parses the query string
$result = $client->attribution()->preview([
    'url' => 'https://example.com/landing?utm_source=tg&utm_medium=messaging&utm_campaign=spring',
]);

$result->category;       // 'messaging'
$result->categoryLabel;  // 'Messaging'
$result->name;           // 'WhatsApp' | 'Telegram' | ...
$result->source;         // 'whatsapp'
$result->medium;         // 'social'
$result->method;         // 'utm' | 'click_id' | 'referrer' | 'direct' | 'custom_rule'
$result->isDirect();     // bool
$result->isPaid();       // bool — true for paid_search / paid_social / etc.
```

The engine runs the same code path that classifies real tracker sessions, so a positive preview is contractual: if the engine says `messaging/WhatsApp` now, a visitor landing with those tags in production will be classified the same way.

### Click IDs + referrer

Both can be passed when you want to simulate something beyond bare UTMs — e.g. checking that a `gclid` lands in `paid_search` even if the merchant forgot to set `utm_medium=cpc`:

```php
$client->attribution()->preview([
    'click_ids' => ['gclid' => 'xyz123'],
    'referrer'  => 'https://www.google.com/search?q=...',
]);
```

### Preset catalog

`presets()` returns the canonical chip catalog the SnipForm app's link builder uses. Render the chip strip in your own link-creation UX so the UTM taxonomy stays consistent between SnipForm and your app:

```php
foreach ($client->attribution()->presets() as $preset) {
    // ['group' => 'Messaging', 'key' => 'whatsapp', 'label' => 'WhatsApp',
    //  'utm_source' => 'whatsapp', 'utm_medium' => 'messaging']
}
```

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

## Laravel

Optional Laravel layer that ships in `SnipForm\Laravel\*`. The SDK doesn't depend on Laravel — these classes only load when `illuminate/support` is installed. Composer's package discovery wires the provider; everything is opt-in via config.

### Reading the visitor's session id

The JS tracker resolves a `sessionId` for the visitor on every page — but only on the client side. The browser is the only canonical source. To use the value in your Laravel controllers (for identify, custom events, analytics joins), drop the `@snipformHydrate` directive into your root Blade layout and the SDK delivers it server-side.

Works for any Laravel app that renders an HTML shell — including Inertia, whose root layout is still a Blade file.

```blade
<head>
  @snipformHydrate
</head>
```

That's all. From any controller:

```php
public function checkout(Request $request, \SnipForm\Client $sf)
{
    $sessionId = $request->snipformSessionId();          // string|null
    // or via facade
    $sessionId = \SnipForm\Laravel\Facades\Snipform::sessionId();

    if ($sessionId) {
        $sf->session()->event([
            'session_id' => $sessionId,
            'name' => 'checkout_started',
        ]);
    }
}
```

The macro reads from, in priority:

1. **Laravel session** — populated by the hydrate loader on a prior request. Free after the first delivery.
2. **`X-SnipForm-Session-Id` header** — set by the JS tracker's `attachToFetch()` on AJAX calls.
3. **`snip_session_id` form field** — set by `attachToForm()` on form posts.
4. **`snip_session_id` query string** — manually appended.

#### How the hydrate loader delivers the value

The directive emits a tiny inline `<script>` block that, once the tracker resolves the `sessionId`, writes it into a transient first-party cookie. The cookie lives for **exactly one request**: a middleware reads the value, copies it into your Laravel session under `snipform_session_id`, and clears the cookie on the response.

```
Tracker boots → sets `site_initializer=<sid>; SameSite=Lax; Path=/`
       ↓
User's next nav → cookie travels automatically
       ↓
Laravel middleware → reads value → session()->put(...) → Set-Cookie deletes it
       ↓
From here on → $request->snipformSessionId() returns it, no cookie involved
```

The cookie is purely transport — a CSRF-shaped baton-handoff. It never persists, never gets observed by anything but your own app, and is gone from the browser after one round-trip. Same "strictly necessary" GDPR category as a CSRF cookie.

The loader is **idempotent**: it bakes the currently-stashed value into the emitted script and skips the cookie write when `window.signals.sessionId` already matches. A page reload after a successful delivery is a complete no-op.

#### Cookie vs POST mode

Two delivery modes, swappable via config:

| Mode | What it does | Use when |
|---|---|---|
| `'cookie'` (default) | JS writes a transient first-party cookie; middleware reads + clears it on the next request | Almost always — zero extra HTTP calls, value rides on the user's next nav |
| `'post'` | JS POSTs `{ session_id }` to a dedicated bind endpoint (`/snipform/bind` by default) | Your infra strips cookies between browser and Laravel (rare — some corporate proxies, weird CDN configs) |

Switch with:

```php
// config/snipform.php
'hydrate' => [
    'mode'        => 'post',                       // default 'cookie'
    'cookie_name' => 'site_initializer',           // default — generic on purpose
    'bind_url'    => '/snipform/bind',             // post mode only
    'enabled'     => true,
],
```

In **post mode** you'll want the standard CSRF meta tag in your layout so the POST request carries a token:

```blade
<meta name="csrf-token" content="{{ csrf_token() }}">
```

In **cookie mode** the meta tag isn't required — the loader doesn't make any HTTP calls.

#### Honest limits

- The very first server-rendered request itself doesn't have a session id — the tracker hasn't booted yet. Every request after the first does.
- JS-disabled visitors never trigger the loader, so the session id never reaches your backend. Identify calls for those visitors still create a `Contact`, just without a session link.

### Auto-identify on login

The happy path: every time a user logs into your app, attach their session to a SnipForm `Contact` automatically. No code at the callsite.

**1. Set the token in `.env`:**

```
SNIPFORM_TOKEN=snipform_pat_xxx
```

**2. Add the `Identifiable` trait to your User model:**

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use SnipForm\Laravel\Concerns\Identifiable;

class User extends Authenticatable
{
    use Identifiable;
}
```

That's it. The provider registers a listener on `Illuminate\Auth\Events\Login` and fires identify against `Snipform` whenever a user authenticates.

**What gets sent.** `Identifiable` auto-derives the payload by probing common columns:

| Where | Columns checked |
|---|---|
| `external_id` | `$user->getKey()` |
| `email` (top level) | `email` |
| `traits.*` | `first_name`, `last_name`, `phone`, `company`, `job_title`, `website`, `country`, `city` |
| `traits.first_name` / `traits.last_name` (fallback) | `name` split on whitespace |

Override what's sent by adding hooks to your model:

```php
class User extends Authenticatable
{
    use Identifiable;

    // Custom external id — defaults to $this->getKey()
    protected function snipformExternalId(): string
    {
        return 'usr_'.$this->uuid;
    }

    // Merged on top of the auto-derived traits
    protected function snipformTraits(): array
    {
        return [
            'company' => $this->team?->name,
            'meta'    => [
                ['key' => 'plan', 'value' => $this->subscription_plan],
            ],
        ];
    }
}
```

**Performance — three layers of "don't overburden the API":**

1. **`afterResponse` queueing.** The identify call runs after Laravel sends the response. Login is never blocked.
2. **Cache-backed dedup.** The provider wires Laravel's default cache as an atomic gate via `Cache::add`. The first request per `(user, payload)` per TTL goes over the wire; the rest are no-ops.
3. **Server-side idempotency.** Even if you bypass the dedup, the SnipForm API merges traits in place — repeat calls with the same payload are a no-op database read.

**Config knobs** (all `.env`-driven):

```
SNIPFORM_IDENTIFY_ON_LOGIN=true       # default — auto-listen on Login event
SNIPFORM_IDENTIFY_QUEUE=true          # default — fire after response is sent
SNIPFORM_IDENTIFY_DEDUP_TTL=3600      # default — 1h; 0 disables dedup
SNIPFORM_IDENTIFY_CACHE_STORE=redis   # optional — named cache store override
```

Full control over the rest by publishing the config file:

```bash
php artisan vendor:publish --tag=snipform-config
```

### Firing events from Laravel

With the hydrate loader in your layout, the visitor's session id is already in the Laravel session - so server-side writes are one line, with no request plumbing:

```php
use SnipForm\Laravel\Facades\Snipform;

Snipform::event('purchase', 9900, ['order' => $order->id]);   // name, value, meta
Snipform::event('newsletter_signup');
Snipform::revenue(9900, 'USD');                                // acquisition value, integer cents
Snipform::acquisition(['cost' => 250, 'tags' => ['paid']]);    // any acquisition fields
```

Each returns `true` when the write was dispatched and **`false` when no session id could be resolved** - a JS-disabled visitor, a bot, or the very first request before the tracker booted. That's a normal outcome, not an exception; your checkout never breaks because analytics couldn't see the visitor.

When you already hold the id (you stored it on an order at checkout via `Snipform::sessionId()`), use the `*For()` variants from anywhere - queued jobs, webhooks, artisan commands:

```php
Snipform::eventFor($order->snipform_session_id, 'order_shipped');
Snipform::acquisitionFor($order->snipform_session_id, ['tags' => ['repeat']]);
```

**When the write leaves the box** is config, not code - `snipform.events.dispatch`:

| Mode | What happens | Use when |
|---|---|---|
| `after_response` (default) | Runs after the response is sent, in the container's terminating phase | Almost always - zero request latency, no worker needed |
| `queue` | A real `ShouldQueue` job (`SignalWriteJob`) on your queue connection, name from `snipform.queue_name` | You run workers and want retries + to survive the request entirely |
| `sync` | Inline; the caller waits for the API | Tests, CLI, or when you need the `Event` back immediately (use `Snipform::session()->event()` for that) |

```
SNIPFORM_EVENTS_DISPATCH=after_response   # sync | after_response | queue
SNIPFORM_QUEUE=snipform                   # queue name for 'queue' mode (optional)
```

### `Snipform` facade

Everything the Laravel backend does with SnipForm, in one place:

```php
use SnipForm\Laravel\Facades\Snipform;

// Writes — session id resolved for you
Snipform::event('purchase', 9900);
Snipform::revenue(9900, 'USD');
Snipform::acquisition(['tags' => ['paid']]);

// Identify
Snipform::identify($user);              // any model with the Identifiable trait (alias: user())
Snipform::auth();                       // identify auth()->user(), no-op for guests
Snipform::payload([                     // raw passthrough — bypass the trait
    'email'  => 'jane@acme.com',
    'traits' => ['first_name' => 'Jane'],
]);

// The visitor's session id, or null
Snipform::sessionId();

// Reads + resources
Snipform::signals()->last7Days()->metrics();
Snipform::contacts()->find($id);
Snipform::session()->event($request, ['name' => 'x']);   // the raw resource when you want the Event back
Snipform::client();                                      // escape hatch — full SDK Client
```

All identify calls honor the dedup + dispatch config — `Snipform::auth()` called a hundred times in the same hour costs you one API call. The dedup decision runs inline (one cache add); only the HTTP call defers.

To register the `Snipform` alias globally (so you don't have to `use` it everywhere), add to `config/app.php`:

```php
'aliases' => Facade::defaultAliases()->merge([
    'Snipform' => \SnipForm\Laravel\Facades\Snipform::class,
])->toArray(),
```

### Identify middleware (SPAs / token APIs)

Some apps don't go through the form login flow — token-auth APIs (`auth:sanctum`, `auth:api`), SPAs that maintain a session via Sanctum's stateful cookie, etc. The `Login` event never fires in those cases.

The `snipform.identify` middleware (alias registered by the provider) runs identify against the auth user on every request it's applied to. Cheap to use because the dedup gate short-circuits repeats:

```php
Route::middleware(['auth:sanctum', 'snipform.identify'])->group(function () {
    Route::get('/me', UserController::class);
    Route::get('/dashboard', DashboardController::class);
});
```

Per-request cost when the cache is warm: one cache hit. When the cache misses: one identify call, deferred until after the response is sent.

To target a non-default guard:

```php
Route::middleware('snipform.identify:web')->group(...);
```

### Manual setup — `Client` injection

If you'd rather skip the Laravel layer and reach for the raw SDK, the `Client` is still bound as a singleton:

```php
public function dashboard(\SnipForm\Client $snipform)
{
    return $snipform->signals()->last7Days()->metrics();
}
```

Config also reads from the legacy `config/services.php → snipform` location for back-compat — apps that wired the SDK before the Laravel layer existed keep working untouched.

To opt out of the auto-registered provider entirely, add to your app's `composer.json`:

```json
"extra": { "laravel": { "dont-discover": ["snipform/php-sdk"] } }
```

### `SnipFormSessionMiddleware`

> **Prefer [the `@snipformHydrate` directive](#reading-the-visitors-session-id) for new code.** This middleware predates the hydrate flow and only inspects per-request transports (header / form / query). It doesn't durably bind the session id to Laravel's session, so subsequent requests in the same visit don't see it unless the tracker re-attaches. Kept for back-compat.

Older / lighter helper that just lifts the visitor's session id off the inbound request and stashes it on `$request->attributes`. Use it when you want the id without invoking the SDK on every request (analytics-only handlers, presence checks, conditional logging).

Pulls from, in priority order:

1. `X-SnipForm-Session-Id` header (set by `signals.js` `attachToFetch()`)
2. `snip_session_id` form field (set by `attachToForm()`)
3. `snip_session_id` query string param

Register on the web/api middleware group:

```php
protected $middlewareGroups = [
    'web' => [
        // ...
        \SnipForm\Laravel\Middleware\SnipFormSessionMiddleware::class,
    ],
];
```

Then in any downstream controller:

```php
public function checkout(Request $request, \SnipForm\Client $snipform)
{
    $sessionId = $request->attributes->get(
        \SnipForm\Laravel\Middleware\SnipFormSessionMiddleware::ATTRIBUTE,
    );

    if ($sessionId) {
        $snipform->session()->event([
            'session_id' => $sessionId,
            'name'       => 'checkout_started',
        ]);
    }
}
```

The Session resource also accepts a Request directly — `$snipform->session()->event($request, [...])` — and pulls the same fields. The middleware is purely a convenience for the "I want the id but don't need the SDK yet" case.

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

In Laravel the same options live in `config/snipform.php` (`php artisan vendor:publish --tag=snipform-config`), all `.env`-driven:

| Key | Env | Default | Notes |
|---|---|---|---|
| `token` | `SNIPFORM_TOKEN` | - | Property API token |
| `base_url` / `path_prefix` / `timeout` / `verify_ssl` | `SNIPFORM_BASE_URL` ... | as above | |
| `hydrate.mode` | `SNIPFORM_HYDRATE_MODE` | `cookie` | `cookie` or `post` - see [hydrate](#reading-the-visitors-session-id) |
| `events.dispatch` | `SNIPFORM_EVENTS_DISPATCH` | `after_response` | `sync` / `after_response` / `queue` |
| `queue_name` | `SNIPFORM_QUEUE` | default queue | For any write in `queue` mode |
| `identify.on_login` | `SNIPFORM_IDENTIFY_ON_LOGIN` | `true` | Auto-identify on `Auth\Events\Login` |
| `identify.queue` | `SNIPFORM_IDENTIFY_QUEUE` | `after_response` | Dispatch mode for identify (booleans still accepted) |
| `identify.dedup_ttl` | `SNIPFORM_IDENTIFY_DEDUP_TTL` | `3600` | Seconds; `0` disables dedup |

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
