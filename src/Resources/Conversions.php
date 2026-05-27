<?php

namespace SnipForm\Resources;

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
 */
class Conversions
{
    private const PATH = 'property/conversions';

    public function __construct(private readonly HttpClient $http) {}

    // ----------------------------------------------------------------------
    // Definition
    // ----------------------------------------------------------------------

    /** @return array<int, Conversion> */
    public function all(): array
    {
        $rows = (array) ($this->http->get(self::PATH)->data('conversions') ?? []);

        return array_map(Conversion::fromArray(...), $rows);
    }

    public function find(string $id): Conversion
    {
        $row = $this->http->get(self::PATH.'/'.$id)->data('conversion');

        return Conversion::fromArray((array) $row);
    }

    /**
     * Start a fluent build. Terminate with `->save()`.
     */
    public function create(): ConversionBuilder
    {
        return new ConversionBuilder($this->http);
    }

    /**
     * Patch basics (name / description / type / value / period / cycle).
     * Steps are replaced separately via `replaceSteps()`.
     */
    public function update(string $id, array $attributes): Conversion
    {
        $row = $this->http->post(self::PATH.'/'.$id, $attributes)->data('conversion');

        return Conversion::fromArray((array) $row);
    }

    /**
     * Replace the entire steps list — atomic. Pass each step as
     * `{name, trigger_type, trigger_config, is_required?}`. For type-safe
     * config shapes, use the fluent `create()` builder pattern instead.
     *
     * @param  array<int, array{name: string, trigger_type: string, trigger_config: array, is_required?: bool}>  $steps
     */
    public function replaceSteps(string $id, array $steps): Conversion
    {
        $row = $this->http->post(self::PATH.'/'.$id.'/steps', ['steps' => $steps])->data('conversion');

        return Conversion::fromArray((array) $row);
    }

    public function publish(string $id): Conversion
    {
        $row = $this->http->post(self::PATH.'/'.$id.'/publish')->data('conversion');

        return Conversion::fromArray((array) $row);
    }

    public function toggle(string $id): Conversion
    {
        $row = $this->http->post(self::PATH.'/'.$id.'/toggle')->data('conversion');

        return Conversion::fromArray((array) $row);
    }

    public function delete(string $id): bool
    {
        return (bool) $this->http->delete(self::PATH.'/'.$id)->data('deleted');
    }

    // ----------------------------------------------------------------------
    // Analytics
    // ----------------------------------------------------------------------

    /**
     * Build analytics queries for one conversion.
     */
    public function for(string $id): ConversionAnalytics
    {
        return new ConversionAnalytics($this->http, $id);
    }

    // ----------------------------------------------------------------------
    // Schema discovery
    // ----------------------------------------------------------------------

    /**
     * Catalog of trigger types, conversion types, segment dimensions, and
     * valid match modes. Use this to drive a UI or to validate inputs
     * client-side before sending writes.
     */
    public function schema(): array
    {
        return $this->http->get(self::PATH.'/schema')->all();
    }
}
