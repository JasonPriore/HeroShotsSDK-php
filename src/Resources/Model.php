<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/**
 * Model-account portal (portal JWT, /api/auth/model). Only for model-type
 * accounts, which are blocked from every /api/v1/* route.
 */
class Model extends AbstractResource
{
    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function updateVat(array $fields): array
    {
        return $this->httpPatch('/api/auth/model/profile/vat', ['json' => $fields]);
    }

    /** @return array<string,mixed> */
    public function stats(): array
    {
        return $this->httpGet('/api/auth/model/stats');
    }

    /** @return array<string,mixed> */
    public function getAvatar(): array
    {
        return $this->httpGet('/api/auth/model/avatar');
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function updateAvatar(array $fields): array
    {
        return $this->httpPatch('/api/auth/model/avatar', ['json' => $fields]);
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function publishAvatar(array $fields = []): array
    {
        return $this->httpPost('/api/auth/model/avatar/publish', $fields ? ['json' => $fields] : []);
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function approvals(array $query = []): array
    {
        return $this->httpGet('/api/auth/model/approvals', $query ? ['query' => $query] : []);
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function approve(int $approvalId, array $fields = []): array
    {
        return $this->httpPost("/api/auth/model/approvals/{$approvalId}/approve", $fields ? ['json' => $fields] : []);
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function reject(int $approvalId, array $fields = []): array
    {
        return $this->httpPost("/api/auth/model/approvals/{$approvalId}/reject", $fields ? ['json' => $fields] : []);
    }
}
