<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by PublicEndpointUrlValidator when a user-supplied URL resolves to a
 * non-public host, so callers can translate it into their own domain exception.
 */
class UnsafeEndpointUrlException extends RuntimeException
{
}
