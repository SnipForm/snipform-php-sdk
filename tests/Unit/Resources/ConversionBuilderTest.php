<?php

namespace Snipform\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Snipform\Http\HttpClient;
use Snipform\Resources\Builders\ConversionBuilder;

/**
 * The ConversionBuilder is fluent — verify each setter and each step-trigger
 * terminal lands the right shape in the internal payload. We never call
 * ->save() so no HTTP fires; the test introspects private state instead.
 */
class ConversionBuilderTest extends TestCase
{
    private function builder(): ConversionBuilder
    {
        return new ConversionBuilder(new HttpClient('test-token', 'https://example.test'));
    }

    private function read(ConversionBuilder $b, string $property): mixed
    {
        $ref = new ReflectionClass($b);
        $prop = $ref->getProperty($property);

        return $prop->getValue($b);
    }

    public function test_basics_set_payload_keys(): void
    {
        $b = $this->builder()
            ->name('Test conversion')
            ->description('A description')
            ->type('lead')
            ->conversionValue(9.99)
            ->valueFromEvent()
            ->startingFrom('2026-01-01')
            ->defaultPeriod('last_30')
            ->defaultCycle('week')
            ->publish();

        $payload = $this->read($b, 'payload');

        $this->assertSame('Test conversion', $payload['name']);
        $this->assertSame('A description', $payload['description']);
        $this->assertSame('lead', $payload['type']);
        $this->assertSame(9.99, $payload['conversion_value']);
        $this->assertTrue($payload['value_from_event']);
        $this->assertSame('2026-01-01', $payload['starting_from']);
        $this->assertSame('last_30', $payload['default_period']);
        $this->assertSame('week', $payload['default_cycle']);
        $this->assertTrue($payload['publish']);
    }

    public function test_page_view_step(): void
    {
        $b = $this->builder()
            ->step('Visit pricing')->onPageView('/pricing', 'exact', 'path');

        $steps = $this->read($b, 'steps');
        $this->assertCount(1, $steps);
        $this->assertSame('Visit pricing', $steps[0]['name']);
        $this->assertSame('page_view', $steps[0]['trigger_type']);
        $this->assertSame([
            'type' => 'page',
            'field' => 'path',
            'match' => 'exact',
            'value' => '/pricing',
        ], $steps[0]['trigger_config']);
        $this->assertTrue($steps[0]['is_required']);
    }

    public function test_entry_page_step_defaults_to_entry_path_field(): void
    {
        $b = $this->builder()
            ->step('Landed on blog')->onEntryPage('/blog');

        $step = $this->read($b, 'steps')[0];
        $this->assertSame('entry_page', $step['trigger_type']);
        $this->assertSame('entry_path', $step['trigger_config']['field']);
        $this->assertSame('contains', $step['trigger_config']['match']);
    }

    public function test_event_step_carries_value_match(): void
    {
        $b = $this->builder()
            ->step('Big purchase')->onEvent('purchase', '50', 'gte');

        $step = $this->read($b, 'steps')[0];
        $this->assertSame('event', $step['trigger_type']);
        $this->assertSame('purchase', $step['trigger_config']['name']);
        $this->assertSame('50', $step['trigger_config']['value']);
        $this->assertSame('gte', $step['trigger_config']['valueMatch']);
    }

    public function test_event_step_with_default_value_match(): void
    {
        $b = $this->builder()
            ->step('Any purchase')->onEvent('purchase');

        $step = $this->read($b, 'steps')[0];
        $this->assertSame('exists', $step['trigger_config']['valueMatch']);
    }

    public function test_form_submit_step(): void
    {
        $b = $this->builder()
            ->step('Form done')->onFormSubmit('snipform_xyz');

        $step = $this->read($b, 'steps')[0];
        $this->assertSame('form_submit', $step['trigger_type']);
        $this->assertSame('snipform_xyz', $step['trigger_config']['snipFormId']);
    }

    public function test_short_link_step_with_group_scope(): void
    {
        $b = $this->builder()
            ->step('From affiliate')->onShortLink('group_abc', 'group');

        $step = $this->read($b, 'steps')[0];
        $this->assertSame('short_link', $step['trigger_type']);
        $this->assertSame('group', $step['trigger_config']['scope']);
        $this->assertSame('group_abc', $step['trigger_config']['value']);
    }

    public function test_optional_step_marks_is_required_false(): void
    {
        $b = $this->builder()
            ->step('Maybe')->optional()->onPageView('/maybe');

        $step = $this->read($b, 'steps')[0];
        $this->assertFalse($step['is_required']);
    }

    public function test_multi_step_chain_preserves_order(): void
    {
        $b = $this->builder()
            ->name('Multi')
            ->type('sale')
            ->step('Step 1')->onPageView('/a')
            ->step('Step 2')->onEvent('add_to_cart')
            ->step('Step 3')->onEvent('purchase');

        $steps = $this->read($b, 'steps');
        $this->assertCount(3, $steps);
        $this->assertSame(['Step 1', 'Step 2', 'Step 3'], array_column($steps, 'name'));
        $this->assertSame(['page_view', 'event', 'event'], array_column($steps, 'trigger_type'));
    }
}
