<?php

namespace SnipForm\Exceptions;

/**
 * Thrown when the SDK is asked to act on a Request that doesn't carry the
 * visitor's session_id (no `X-SnipForm-Session-Id` header, no
 * `snip_session_id` form field, no explicit override).
 *
 * Typical fix: confirm signals.js is loaded on the page and that
 * `window.signals.attachToFetch()` / `attachToForms()` ran before the
 * request was issued. Or pass `session_id` explicitly in the call.
 */
class MissingSessionIdException extends SnipFormException
{
    public function __construct(string $message = 'No session_id on this request — is signals.js loaded and bridged via attachToFetch() / attachToForms()? Or pass session_id explicitly.')
    {
        parent::__construct($message);
    }
}
