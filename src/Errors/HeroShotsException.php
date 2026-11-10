<?php

declare(strict_types=1);

namespace HeroShots\Errors;

use RuntimeException;

/**
 * Base class for every exception raised by the SDK.
 *
 * {@see ApiException} (and its subclasses) covers errors returned by the API;
 * a plain HeroShotsException is raised for client-side misuse, e.g. passing a
 * local file path while authenticated with an API key.
 */
class HeroShotsException extends RuntimeException
{
}
