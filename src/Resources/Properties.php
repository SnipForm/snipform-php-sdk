<?php

namespace SnipForm\Resources;

use SnipForm\Concerns\RawAware;
use SnipForm\Data\PropertyOverview;
use SnipForm\Http\HttpClient;

/**
 * Property-level reads. Currently exposes the overview endpoint that returns
 * the property identity, state, and headline counts.
 *
 *   $property = $client->properties()->overview();             // PropertyOverview
 *   $raw      = $client->properties()->asRaw()->overview();    // array
 */
class Properties
{
    use RawAware;

    public function __construct(private readonly HttpClient $http) {}

    public function overview(): PropertyOverview|array
    {
        $row = (array) $this->http->get('property/overview')->data();

        return $this->hydrate($row, PropertyOverview::fromArray(...));
    }
}
