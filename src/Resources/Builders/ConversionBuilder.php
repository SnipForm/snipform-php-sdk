<?php

namespace SnipForm\Resources\Builders;

use SnipForm\Http\HttpClient;
use SnipForm\Resources\Conversion;

/**
 * Fluent builder for creating a new conversion definition. Returned by
 * `$client->conversions()->create()`; call `->save()` when the chain is built.
 *
 *   $client->conversions()->create()
 *       ->name('Newsletter signup')
 *       ->type('lead')
 *       ->conversionValue(5.00)
 *       ->step('Visit pricing')->onPageView('/pricing')
 *       ->step('Submit form')->onFormSubmit('snipform_xyz')
 *       ->publish()
 *       ->save();
 */
class ConversionBuilder
{
    private const PATH = 'property/conversions';

    private array $payload = [
        'name' => null,
        'description' => null,
        'type' => null,
        'conversion_value' => null,
        'value_from_event' => null,
        'starting_from' => null,
        'default_period' => null,
        'default_cycle' => null,
        'publish' => false,
    ];

    private array $steps = [];

    public function __construct(private readonly HttpClient $http) {}

    // ----------------------------------------------------------------------
    // Basics
    // ----------------------------------------------------------------------

    public function name(string $name): self
    {
        $this->payload['name'] = $name;

        return $this;
    }

    public function description(string $description): self
    {
        $this->payload['description'] = $description;

        return $this;
    }

    /**
     * @param  string  $type  lead | sale | signup | activation | download | custom
     */
    public function type(string $type): self
    {
        $this->payload['type'] = $type;

        return $this;
    }

    public function conversionValue(float $value): self
    {
        $this->payload['conversion_value'] = $value;

        return $this;
    }

    /**
     * Take the conversion value from the matching event's `value` field
     * instead of the fixed `conversion_value`. Only meaningful if the
     * final step is an event trigger.
     */
    public function valueFromEvent(bool $on = true): self
    {
        $this->payload['value_from_event'] = $on;

        return $this;
    }

    /**
     * Clamp the conversion's data window — sessions before this date are
     * never counted, even if the caller passes an earlier `from`.
     */
    public function startingFrom(string $isoDate): self
    {
        $this->payload['starting_from'] = $isoDate;

        return $this;
    }

    public function defaultPeriod(string $period): self
    {
        $this->payload['default_period'] = $period;

        return $this;
    }

    public function defaultCycle(string $cycle): self
    {
        $this->payload['default_cycle'] = $cycle;

        return $this;
    }

    /**
     * Publish on save instead of leaving as draft. Requires ≥1 step.
     */
    public function publish(): self
    {
        $this->payload['publish'] = true;

        return $this;
    }

    // ----------------------------------------------------------------------
    // Steps
    // ----------------------------------------------------------------------

    /**
     * Start building the next funnel step. Chain `->onPageView()`, `->onEvent()`,
     * etc. on the returned StepBuilder to commit the step and continue.
     */
    public function step(string $name): StepBuilder
    {
        return (new StepBuilder($this))->name($name);
    }

    /**
     * Used by StepBuilder to commit a fully-formed step row. Most callers
     * should use `->step()->on*()` instead.
     */
    public function addRawStep(array $step): self
    {
        $this->steps[] = $step;

        return $this;
    }

    // ----------------------------------------------------------------------
    // Commit
    // ----------------------------------------------------------------------

    public function save(): Conversion
    {
        $body = array_filter(
            $this->payload,
            fn ($v) => $v !== null,
        );
        if (! empty($this->steps)) {
            $body['steps'] = $this->steps;
        }

        $row = $this->http->post(self::PATH, $body)->data('conversion');

        return Conversion::fromArray((array) $row);
    }
}
