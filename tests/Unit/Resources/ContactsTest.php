<?php

namespace SnipForm\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use SnipForm\Data\Contact;
use SnipForm\Tests\Support\FakeApi;
use Symfony\Component\HttpFoundation\Request;

class ContactsTest extends TestCase
{
    private function contactRow(): array
    {
        return ['contact' => ['id' => 'c1', 'email' => 'jane@acme.com', 'first_name' => 'Jane', 'full_name' => 'Jane', 'state' => 'active']];
    }

    public function test_identify_posts_the_payload_and_hydrates_a_contact(): void
    {
        $api = FakeApi::make()->willReturn($this->contactRow());

        $contact = $api->client->contacts()->identify(['email' => 'jane@acme.com', 'traits' => ['first_name' => 'Jane']]);

        $this->assertInstanceOf(Contact::class, $contact);
        $this->assertSame('jane@acme.com', $contact->email);
        $this->assertSame('/v2/property/contacts/identify', $api->lastRequest()->getUri()->getPath());
    }

    public function test_without_a_dedup_gate_every_payload_passes(): void
    {
        $api = FakeApi::make();

        $this->assertTrue($api->client->contacts()->passesDedup(['email' => 'jane@acme.com']));
    }

    public function test_the_dedup_gate_short_circuits_repeats_without_any_http(): void
    {
        $seen = [];
        $api = FakeApi::make()->willReturn($this->contactRow());
        $api->client->withIdentifyDedup(function (string $key, int $ttl) use (&$seen): bool {
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = $ttl;

            return true;
        }, 600);

        $this->assertTrue($api->client->contacts()->identifyOnce(['email' => 'jane@acme.com']));
        // Second call: no queued response remains, so any HTTP attempt would throw.
        $this->assertFalse($api->client->contacts()->identifyOnce(['email' => 'jane@acme.com']));
        $this->assertSame([600], array_values($seen));
    }

    public function test_fingerprint_ignores_key_order(): void
    {
        $keys = [];
        $api = FakeApi::make();
        $api->client->withIdentifyDedup(function (string $key) use (&$keys): bool {
            $keys[] = $key;

            return true;
        });

        $api->client->contacts()->passesDedup(['email' => 'a@b.c', 'traits' => ['x' => 1, 'y' => 2]]);
        $api->client->contacts()->passesDedup(['traits' => ['y' => 2, 'x' => 1], 'email' => 'a@b.c']);

        $this->assertCount(2, $keys);
        $this->assertSame($keys[0], $keys[1]);
    }

    public function test_identify_from_request_merges_the_tracker_header(): void
    {
        $api = FakeApi::make()->willReturn($this->contactRow());
        $request = Request::create('/login', 'POST');
        $request->headers->set('X-Snipform-Session-Id', 'sess_42');

        $api->client->contacts()->identifyFromRequest($request, ['email' => 'jane@acme.com']);

        $this->assertSame('sess_42', $api->lastJson()['session_id']);
    }

    public function test_delete_returns_the_deleted_flag(): void
    {
        $api = FakeApi::make()->willReturn(['deleted' => true]);

        $this->assertTrue($api->client->contacts()->delete('c1'));
        $this->assertSame('DELETE', $api->lastRequest()->getMethod());
        $this->assertSame('/v2/property/contacts/c1', $api->lastRequest()->getUri()->getPath());
    }
}
