<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/** Account endpoints on the public API (API key or portal JWT). */
class Account extends AbstractResource
{
    /** @return array<string,mixed> GET /api/v1/me -> {user, credits_remaining: {hs_credits, currency_credit}} */
    public function me(): array
    {
        return $this->httpGet('/api/v1/me');
    }

    /** @return array<string,mixed> GET /api/v1/health -> service health (no auth needed) */
    public function health(): array
    {
        return $this->httpGet('/api/v1/health', ['auth' => false]);
    }
}
