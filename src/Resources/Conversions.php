<?php

namespace SnipForm\Resources;

use SnipForm\Concerns\RawAware;
use SnipForm\Data\Conversion;
use SnipForm\Http\HttpClient;
use SnipForm\Resources\Builders\ConversionAnalytics;
use SnipForm\Resources\Builders\ConversionBuilder;

/**
 * Conversions resource. Two surfaces:
 *
 *  - Definition CRUD — list / find / create / update / replaceSteps /
 *    publish / toggle / delete. Plus a `schema()` lookup that returns the
 *    catalog of valid trigger types so you can build configs without
 *    hardcoding shape.
 *
 *  - Analytics reads — `->for($id)` returns a ConversionAnalytics that
 *    can be chained: `->between(...)->filter(...)->summary()`.
 *
 *   $c = $client->conversions()->create()
 *       ->name('Newsletter')->type('lead')
 *       ->step('Visit')->onPageView('/pricing')
 *       ->step('Submit')->onFormSubmit($formId)
 *       ->save();
 *
 *   $client->conversions()->for($c->id)->since(strtotime('-30 days'))->summary();
 *
 * Append `->asRaw()` to get arrays back instead of Conversion DTOs (the
 * flag is forwarded into the ConversionBuilder + ConversionAnalytics too).
 */
class Conversions
{
    use RawAware;

    private const PATH = 'property/conversions';

    public function __construct(private readonly HttpClient $http) {}

    // ----------------------------------------------------------------------
    // Definition
    // ----------------------------------------------------------------------

    /** @return array<int, Conversion>|array<int, array> */
    public function all(): array
    {
        $rows = (array) ($this->http->get(self::PATH)->data('conversions') ?? []);

        return array_map(fn (array $row) => $this->hydrate($row, Conversion::fromArray(...)), $rows);
    }

    public function find(string $id): Conversion|array
    {
        $row = (array) $this->http->get(self::PATH.'/'.$id)->data('conversion');

        return $this->hydrate($row, Conversion::fromArray(...));
    }

    /**
     * Start a fluent build. Terminate with `->save()`. Inherits the parent
     * `asRaw` flag so `$client->conversions()->asRaw()->create()->save()`
     * returns an array.
     */
    public function create(): ConversionBuilder
    {
        return (new ConversionBuilder($this->http))->asRaw($this->asRaw);
    }

    /**
     * Patch basics (name / description / type / value / period / cycle).
     * Steps are replaced separately via `replaceSteps()`.
     */
    public function update(string $id, array $attributes): Conversion|array
    {
        $row = (array) $this->http->post(self::PATH.'/'.$id, $attributes)->data('conversion');

        return $this->hydrate($row, Conversion::fromArray(...));
    }

    /**
     * Replace the entire steps list — atomic. Pass each step as
     * `{name, trigger_type, trigger_config, is_required?}`. For type-safe
     * config shapes, use the fluent `create()` builder pattern instead.
     *
     * @param  array<int, array{name: string, trigger_type: string, trigger_config: array, is_required?: bool}>  $steps
     */
    public function replaceSteps(string $id, array $steps): Conversion|array
    {
        $row = (array) $this->http->post(self::PATH.'/'.$id.'/steps', ['steps' => $steps])->data('conversion');

        return $this->hydrate($row, Conversion::fromArray(...));
    }

    public function publish(string $id): Conversion|array
    {
        $row = (array) $this->http->post(self::PATH.'/'.$id.'/publish')->data('conversion');

        return $this->hydrate($row, Conversion::fromArray(...));
    }

    public function toggle(string $id): Conversion|array
    {
        $row = (array) $this->http->post(self::PATH.'/'.$id.'/toggle')->data('conversion');

        return $this->hydrate($row, Conversion::fromArray(...));
    }

    public function delete(string $id): bool
    {
        return (bool) $this->http->delete(self::PATH.'/'.$id)->data('deleted');
    }

    // ----------------------------------------------------------------------
    // Analytics
    // ----------------------------------------------------------------------

    /**
     * Build analytics queries for one conversion. Inherits the parent
     * `asRaw` flag.
     */
    public function for(string $id): ConversionAnalytics
    {
        return (new ConversionAnalytics($this->http, $id))->asRaw($this->asRaw);
    }

    // ----------------------------------------------------------------------
    // Schema discovery
    // ----------------------------------------------------------------------

    /**
     * Catalog of trigger types, conversion types, segment dimensions, and
     * valid match modes. Always returns the raw array — there's no typed
     * shape worth hydrating here.
     */
    public function schema(): array
    {
        return $this->http->get(self::PATH.'/schema')->data();
    }
}
