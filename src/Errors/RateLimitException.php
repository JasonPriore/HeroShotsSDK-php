<?php

declare(strict_types=1);

namespace HeroShots\Errors;

/** HTTP 429 - too many requests. $retryAfter holds the server hint in seconds. */
class RateLimitException extends ApiException
{
    public ?float $retryAfter = null;
}
