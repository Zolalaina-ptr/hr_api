<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business rule violation (e.g. insufficient leave balance, overlapping
 * leaves, state not allowing the operation). Rendered as HTTP 422 for API
 * requests instead of an unhandled 500 error.
 */
class BusinessRuleException extends RuntimeException
{
}
