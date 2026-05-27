<?php

namespace SnipForm;

use SnipForm\Http\HttpClient;
use SnipForm\Resources\Clicks;
use SnipForm\Resources\Conversions;
use SnipForm\Resources\LinkGroups;
use SnipForm\Resources\Links;
use SnipForm\Resources\Session;
use SnipForm\Resources\Signals;

/**
 * Top-level SnipForm client. Holds auth + HTTP, exposes resource sub-clients.
 */
class Client
{
    private const DEFAULT_BASE_URL = 'https://app.snipform.io';

    public readonly HttpClient $http;

    public function __construct(string $token, array $options = [])
    {
        $this->http = new HttpClient(
            token: $token,
            baseUrl: $options['base_url'] ?? self::DEFAULT_BASE_URL,
            timeout: $options['timeout'] ?? 30,
        );
    }

    public function signals(): Signals
    {
        return new Signals($this->http);
    }

    public function session(): Session
    {
        return new Session($this->http);
    }

    public function linkGroups(): LinkGroups
    {
        return new LinkGroups($this->http);
    }

    public function links(): Links
    {
        return new Links($this->http);
    }

    public function clicks(): Clicks
    {
        return new Clicks($this->http);
    }

    public function conversions(): Conversions
    {
        return new Conversions($this->http);
    }
}
