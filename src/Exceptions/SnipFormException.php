<?php

namespace SnipForm\Exceptions;

use Exception;

/**
 * Base for everything the SDK throws. Catch this if you want to handle any
 * SnipForm-side failure as a single class of error.
 */
class SnipFormException extends Exception {}
