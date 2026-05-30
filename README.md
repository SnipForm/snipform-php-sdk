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
  - [Pages](#pages--explicit-pagination)
  - [Metrics](#metrics)
- [Short links](#short-links)
- [Session actions](#session-actions)
- [Conversions](#conversions)
- [Attribution](#attribution)
- [`asRaw()` — opt-out of typed objects](#asraw--opt-out-of-typed-objects)
- [Laravel](#laravel)
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
SNIPFORM_IDENTIFY_DEDUP_TTL=86400     # default — 24h; 0 disables dedup
SNIPFORM_IDENTIFY_CACHE_STORE=redis   # optional — named cache store override
```

Full control over the rest by publishing the config file:

```bash
php artisan vendor:publish --tag=snipform-config
```

### `Snipform` facade

Explicit identify calls — useful for paths the `Login` event doesn't cover (impersonation, webhook handlers, side flows):

```php
use SnipForm\Laravel\Facades\Snipform;

Snipform::auth();                       // identify auth()->user(), no-op for guests
Snipform::user($someOtherUser);         // identify any model with the Identifiable trait
Snipform::payload([                     // raw passthrough — bypass the trait
    'email'  => 'jane@acme.com',
    'traits' => ['first_name' => 'Jane'],
]);

Snipform::contacts()->find($id);        // SDK Contacts resource — find/update/delete/all
Snipform::contacts()->update($id, [
    'lifecycle_stage' => 'customer',
]);

Snipform::client();                     // escape hatch — full SDK Client
```

All `identify` calls honor the same dedup + queue config — `Snipform::auth()` called a hundred times in the same hour costs you one API call.

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
