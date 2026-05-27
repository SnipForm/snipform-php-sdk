<?php

namespace Snipform;

use Snipform\Http\HttpClient;
use Snipform\Resources\Clicks;
use Snipform\Resources\Conversions;
use Snipform\Resources\LinkGroups;
use Snipform\Resources\Links;
use Snipform\Resources\Session;
use Snipform\Resources\Signals;

/**
 * Top-level Snipform client. Holds auth + HTTP, exposes resource sub-clients.
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
