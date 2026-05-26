<?php

namespace Snipform\Exceptions;

use Exception;

/**
 * Base for everything the SDK throws. Catch this if you want to handle any
 * Snipform-side failure as a single class of error.
 */
class SnipformException extends Exception {}
