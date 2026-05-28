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

## DTOs

Every typed return object extends `SnipForm\Resources\SnipFormDTO` and exposes two helpers:

```php
$dto->toArray();                  // public fields as an array, raw excluded
$dto->raw();                      // original API payload
$dto->raw('field');               // single field from the payload, or null
$dto->raw('acquisition.value');   // dot-path into nested arrays, or null
```

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
$property->raw();          // full unwrapped data block
```

## Query builder

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
->between('2026-01-01', '2026-01-31')    // custom range

// Or via the enum:
use SnipForm\Query\Period;
->period(Period::LAST_28)
->period('last_28')                      // string also fine; validated upfront
```

Invalid period strings throw `SnipForm\Exceptions\InvalidPeriodException` immediately — no HTTP round-trip.

### Sessions, lazy

`->sessions()` returns a `SessionCollection` you can iterate. Each iteration step pulls the next page transparently.

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
$m->raw();             // full unwrapped body for anything not surfaced
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

Submit a custom event for a session. Requires `session_id` (from a prior `resolve()`).

```php
$event = $snipform->session()->event([
    'session_id' => $resolved->sessionId,
    'name'       => 'purchase',
    'value'      => 99.99,            // optional
    'meta'       => ['order_id' => 'X-1', 'currency' => 'USD'],  // optional
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

## Authentication

The SDK takes a property-scoped Personal Access Token. Generate one in **Property → Settings → API Tokens**. Tokens carry scope (e.g. `signals:read`) — the SDK forwards them and the API enforces.

## Error handling

```php
use SnipForm\Exceptions\AuthenticationException;
use SnipForm\Exceptions\ApiException;
use SnipForm\Exceptions\SnipFormException;

try {
    $sessions = $snipform->signals()->sessions()->all();
} catch (AuthenticationException $e) {
    // 401 / 403 — token bad or out of scope
} catch (ApiException $e) {
    // 4xx / 5xx with a structured body — see $e->status, $e->errors, $e->body
} catch (SnipFormException $e) {
    // any other SDK-side failure (transport, JSON decode, etc.)
}
```

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

```bash
composer install
vendor/bin/phpunit
```
