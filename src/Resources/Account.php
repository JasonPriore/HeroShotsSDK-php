<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/** Account & profile (portal JWT, /api/auth/*). */
class Account extends AbstractResource
{
    /** @return array<string,mixed> */
    public function me(): array
    {
        return $this->httpGet('/api/auth/me');
    }

    /** @return array<string,mixed> */
    public function getProfile(): array
    {
        return $this->httpGet('/api/auth/profile');
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function updateProfile(array $fields): array
    {
        return $this->httpPut('/api/auth/profile', ['json' => $fields]);
    }

    /** @return array<string,mixed> */
    public function stats(): array
    {
        return $this->httpGet('/api/auth/stats');
    }

    /** @return array<string,mixed> */
    public function getPlan(): array
    {
        return $this->httpGet('/api/auth/plan');
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function setPlan(array $fields): array
    {
        return $this->httpPost('/api/auth/plan', ['json' => $fields]);
    }

    /** @return array<string,mixed> */
    public function cancelPlan(): array
    {
        return $this->httpPost('/api/auth/plan/cancel');
    }

    /** @return array<string,mixed> */
    public function getDefaultUsageRights(): array
    {
        return $this->httpGet('/api/auth/profile/default-usage-rights');
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function setDefaultUsageRights(array $fields): array
    {
        return $this->httpPut('/api/auth/profile/default-usage-rights', ['json' => $fields]);
    }

    /** @return array<string,mixed> */
    public function getWatermarkLogo(): array
    {
        return $this->httpGet('/api/auth/profile/watermark-logo');
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function setWatermarkLogo(array $fields): array
    {
        return $this->httpPost('/api/auth/profile/watermark-logo', ['json' => $fields]);
    }
}
