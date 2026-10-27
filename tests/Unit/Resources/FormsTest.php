<?php

namespace SnipForm\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use SnipForm\Data\Form;
use SnipForm\Exceptions\AuthenticationException;
use SnipForm\Tests\Support\FakeApi;

class FormsTest extends TestCase
{
    private function formsBody(): array
    {
        return ['forms' => [
            ['id' => 'f1', 'name' => 'Contact us', 'state' => 'active', 'submits' => 12, 'last_submit_at' => '2026-10-20T09:00:00+00:00', 'created_at' => '2026-01-02T00:00:00+00:00'],
            ['id' => 'f2', 'name' => 'Old quote form', 'state' => 'archived', 'submits' => 0, 'last_submit_at' => null, 'created_at' => '2025-06-01T00:00:00+00:00'],
        ]];
    }

    public function test_all_gets_the_property_forms_and_hydrates_them(): void
    {
        $api = FakeApi::make()->willReturn($this->formsBody());

        $forms = $api->client->forms()->all();

        $this->assertSame('GET', $api->lastRequest()->getMethod());
        $this->assertSame('/v2/property/forms', $api->lastRequest()->getUri()->getPath());
        $this->assertCount(2, $forms);
        $this->assertInstanceOf(Form::class, $forms[0]);
        $this->assertSame('Contact us', $forms[0]->name);
        $this->assertSame(12, $forms[0]->submits);
        $this->assertSame('archived', $forms[1]->state);
        $this->assertNull($forms[1]->lastSubmitAt);
    }

    public function test_raw_mode_returns_the_api_rows(): void
    {
        $api = FakeApi::make()->willReturn($this->formsBody());

        $forms = $api->client->forms()->asRaw()->all();

        $this->assertSame($this->formsBody()['forms'], $forms);
    }

    public function test_a_property_without_forms_is_an_empty_list(): void
    {
        $api = FakeApi::make()->willReturn(['forms' => []]);

        $this->assertSame([], $api->client->forms()->all());
    }

    public function test_a_token_without_forms_read_is_refused(): void
    {
        $api = FakeApi::make()->willRespond(403, ['message' => 'Missing scope: forms:read']);

        $this->expectException(AuthenticationException::class);

        $api->client->forms()->all();
    }
}
