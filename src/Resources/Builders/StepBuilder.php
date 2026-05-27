<?php

namespace SnipForm\Resources\Builders;

/**
 * Builder for one funnel step. Each `on*()` method picks the trigger and
 * returns the parent ConversionBuilder so chaining stays flat.
 *
 *   $client->conversions()->create()
 *       ->name('Newsletter signup')->type('lead')
 *       ->step('Visit pricing')->onPageView('/pricing')
 *       ->step('Submit form')->onFormSubmit($snipFormId)
 *       ->save();
 */
class StepBuilder
{
    private ?string $name = null;

    private bool $isRequired = true;

    public function __construct(private readonly ConversionBuilder $parent) {}

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function optional(): self
    {
        $this->isRequired = false;

        return $this;
    }

    // ----------------------------------------------------------------------
    // Trigger terminals — each adds the step to the parent and returns it
    // so the caller continues on the conversion builder.
    // ----------------------------------------------------------------------

    /**
     * Visit a page anywhere in the session.
     *
     * @param  string  $value  the path or URL to match
     * @param  string  $match  contains | exact | starts_with | regex
     * @param  string  $field  path | url
     */
    public function onPageView(string $value, string $match = 'contains', string $field = 'path'): ConversionBuilder
    {
        return $this->commit('page_view', [
            'type' => 'page',
            'field' => $field,
            'match' => $match,
            'value' => $value,
        ]);
    }

    /**
     * Session entered on a page matching this URL/path.
     *
     * @param  string  $match  contains | exact | starts_with | regex
     * @param  string  $field  entry_path | entry_url
     */
    public function onEntryPage(string $value, string $match = 'contains', string $field = 'entry_path'): ConversionBuilder
    {
        return $this->commit('entry_page', [
            'type' => 'entryPage',
            'field' => $field,
            'match' => $match,
            'value' => $value,
        ]);
    }

    /**
     * Custom event fires. Pass `valueMatch` and `value` to also gate on the
     * event value (e.g. `purchase` with value ≥ 50).
     *
     * @param  string  $valueMatch  exists | equals | gt | gte | lt | lte
     */
    public function onEvent(string $eventName, ?string $value = null, string $valueMatch = 'exists'): ConversionBuilder
    {
        return $this->commit('event', [
            'type' => 'event',
            'name' => $eventName,
            'value' => $value ?? '',
            'valueMatch' => $valueMatch,
        ]);
    }

    /**
     * Submission of a specific SnipForm.
     */
    public function onFormSubmit(string $snipFormId): ConversionBuilder
    {
        return $this->commit('form_submit', [
            'type' => 'formSubmit',
            'snipFormId' => $snipFormId,
        ]);
    }

    /**
     * Session arrived via a short link (or any link in a group).
     *
     * @param  string  $scope  link | group
     */
    public function onShortLink(string $id, string $scope = 'link'): ConversionBuilder
    {
        return $this->commit('short_link', [
            'type' => 'shortLink',
            'scope' => $scope,
            'value' => $id,
        ]);
    }

    private function commit(string $triggerType, array $triggerConfig): ConversionBuilder
    {
        $this->parent->addRawStep([
            'name' => $this->name ?? '',
            'trigger_type' => $triggerType,
            'trigger_config' => $triggerConfig,
            'is_required' => $this->isRequired,
        ]);

        return $this->parent;
    }
}
