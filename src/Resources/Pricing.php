<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/** Pricing & quoting (portal JWT). Use quote() to preview cost before generating. */
class Pricing extends AbstractResource
{
    /** @return array<string,mixed> */
    public function catalog(): array
    {
        return $this->httpGet('/api/auth/pricing/catalog');
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> POST /api/auth/pricing/quote */
    public function quote(string $callType, array $payload = []): array
    {
        return $this->httpPost('/api/auth/pricing/quote', ['json' => array_merge(['call_type' => $callType], $payload)]);
    }

    /** @return array<string,mixed> */
    public function videoPackagesCatalog(): array
    {
        return $this->httpGet('/api/auth/billing/video-packages/catalog');
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function videoPackagesQuote(array $fields): array
    {
        return $this->httpPost('/api/auth/billing/video-packages/quote', ['json' => $fields]);
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function purchaseVideoPackage(array $fields): array
    {
        return $this->httpPost('/api/auth/billing/video-packages/purchase', ['json' => $fields]);
    }

    /** @return array<string,mixed> */
    public function listVideoPackages(): array
    {
        return $this->httpGet('/api/auth/billing/video-packages');
    }

    /** @return array<string,mixed> */
    public function usageRightsRules(): array
    {
        return $this->httpGet('/api/v1/pricing/usage-rights/rules', ['unwrap' => false]);
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function usageRightsQuote(string $logId, array $fields = []): array
    {
        return $this->httpPost("/api/auth/generations/{$logId}/usage-rights/quote", ['json' => $fields]);
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function usageRightsUpgrade(string $logId, array $fields = []): array
    {
        return $this->httpPost("/api/auth/generations/{$logId}/usage-rights/upgrade", ['json' => $fields]);
    }
}
