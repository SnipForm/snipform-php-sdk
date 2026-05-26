# Snipform PHP SDK

Official PHP SDK for the [Snipform](https://snipform.io) API. Eloquent-flavoured query builder over the V2 endpoints.

```bash
composer require snipform/sdk
```

## Quick start

```php
use Snipform\Snipform;

$snipform = Snipform::client('snipform_pat_xxx');

// List sessions matching a query — auto-paginated
foreach ($snipform->signals()
    ->period('last_30')
    ->where('country', 'US')
    ->whereStartsWith('entry_path', '/blog')
    ->sessions() as $session
) {
    echo $session->entryPath.' from '.$session->source.PHP_EOL;
}

// Headline metrics for the same query
$metrics = $snipform->signals()
    ->period('last_7')
    ->where('utm_content', 'pub_12345')   // an affiliate
    ->metrics();

echo "Sessions: {$metrics->sessions}, bounce: {$metrics->bounceRate}%";
```

## Query builder

| Method | DSL emitted | Use for |
|---|---|---|
| `where($field, $value)` | `field:value` or `field:a,b,c` | equality / `IN` |
| `orWhere(...)` | `or_field:value` | OR clause |
| `whereNot(...)` | `not_field:value` | negate |
| `orWhereNot(...)` | `or_not_field:value` | OR negate |
| `whereStartsWith($field, $v)` | `field:v*` | prefix |
| `whereContains($field, $v)` | `field:*v*` | substring |
| `whereRegex($field, $pat)` | `field:/pat/` | regex |
| `whereGt / Gte / Lt / Lte` | `field:>n` etc. | numeric comparison |
| `whereBetween($f, $a, $b)` | `field:[a TO b]` | range (numeric) |
| `whereExists($f)` | `field` | field is present |
| `whereNotExists($f)` | `not_field` | field is absent |
| `raw(...$strings)` | as-is | escape hatch |

### Periods

```php
->period('last_7')                       // named bucket
->between('2026-01-01', '2026-01-31')    // custom range
```

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
$m = $snipform->signals()->period('last_30')->metrics();
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

## Authentication

The SDK takes a property-scoped Personal Access Token. Generate one in **Property → Settings → API Tokens**. Tokens carry scope (e.g. `signals:read`) — the SDK forwards them and the API enforces.

## Error handling

```php
use Snipform\Exceptions\AuthenticationException;
use Snipform\Exceptions\ApiException;
use Snipform\Exceptions\SnipformException;

try {
    $sessions = $snipform->signals()->sessions()->all();
} catch (AuthenticationException $e) {
    // 401 / 403 — token bad or out of scope
} catch (ApiException $e) {
    // 4xx / 5xx with a structured body — see $e->status, $e->errors, $e->body
} catch (SnipformException $e) {
    // any other SDK-side failure (transport, JSON decode, etc.)
}
```

## Configuration

```php
Snipform::client('snipform_pat_xxx', [
    'base_url' => 'https://app.snipform.io',  // default
    'timeout'  => 30,                          // seconds
]);
```

## Tests

```bash
composer install
vendor/bin/phpunit
```
