<?php

declare(strict_types=1);

namespace HeroShots\Errors;

/** The portal JWT expired. There is no refresh endpoint - re-login to renew. */
class TokenExpiredException extends AuthException
{
}
