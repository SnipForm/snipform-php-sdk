<?php

namespace SnipForm\Resources;

use SnipForm\Data\PropertyOverview;
use SnipForm\Http\HttpClient;

/**
 * Property-level reads. Currently exposes the overview endpoint that returns
 * the property identity, state, and headline counts.
 *
 *   $property = $client->properties()->overview();
 */
class Properties
{
    public function __construct(private readonly HttpClient $http) {}

    public function overview(): PropertyOverview
    {
        $data = $this->http->get('property/overview')->data();

        return PropertyOverview::fromArray((array) $data);
    }
}
