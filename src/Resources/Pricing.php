<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/** Public price list and quotes (API key or portal JWT, /api/v1/pricing). */
class Pricing extends AbstractResource
{
    /** @return array<string,mixed> GET /api/v1/pricing/catalog -> image/video prices for the caller's plan */
    public function catalog(): array
    {
        return $this->httpGet('/api/v1/pricing/catalog');
    }

    /**
     * POST /api/v1/pricing/quote -> cost of one generation before running it.
     *
     * $callType is "image", "video" or "background". For videos pass
     * duration_seconds; usage-rights fields are accepted as well.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function quote(string $callType, array $payload = []): array
    {
        return $this->httpPost('/api/v1/pricing/quote', ['json' => array_merge(['call_type' => $callType], $payload)]);
    }

    /** @return array<string,mixed> GET /api/v1/pricing/video-packages/catalog -> volume package tiers */
    public function videoPackagesCatalog(): array
    {
        return $this->httpGet('/api/v1/pricing/video-packages/catalog');
    }

    /**
     * POST /api/v1/pricing/video-packages/quote ($tier: starter | growth | scale).
     *
     * @param array<string,mixed> $payload
     * @param array<int,array<string,mixed>>|null $usageRights
     * @return array<string,mixed>
     */
    public function videoPackagesQuote(string $tier, array $payload = [], ?array $usageRights = null): array
    {
        $body = self::compact(array_merge(['tier' => $tier, 'usage_rights' => $usageRights], $payload));
        return $this->httpPost('/api/v1/pricing/video-packages/quote', ['json' => $body]);
    }

    /** @return array<string,mixed> GET /api/v1/pricing/usage-rights/rules -> allowed usage-rights combinations */
    public function usageRightsRules(): array
    {
        return $this->httpGet('/api/v1/pricing/usage-rights/rules');
    }
}
