<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/** Generation history & pinned items (portal JWT, /api/auth/generations). */
class Generations extends AbstractResource
{
    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function list(array $query = []): array
    {
        return $this->httpGet('/api/auth/generations', $query ? ['query' => $query] : []);
    }

    /** @return array<string,mixed> */
    public function details(int $logId): array
    {
        return $this->httpGet("/api/auth/generations/{$logId}/details");
    }

    /** @return array<string,mixed> */
    public function replicaData(int $logId): array
    {
        return $this->httpGet("/api/auth/generations/{$logId}/replica-data");
    }

    /** @return array<string,mixed> */
    public function previewImage(int $logId): array
    {
        return $this->httpGet("/api/auth/generations/{$logId}/preview-image");
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function download(int $logId, array $fields = []): array
    {
        return $this->httpPost("/api/auth/generations/{$logId}/download", $fields ? ['json' => $fields] : []);
    }

    /** @return array<string,mixed> */
    public function delete(int $logId): array
    {
        return $this->httpDelete("/api/auth/generations/{$logId}");
    }

    /** @return array<string,mixed> */
    public function view(string $token): array
    {
        return $this->httpGet("/api/auth/generation/view/{$token}");
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function requestApproval(int $logId, array $fields = []): array
    {
        return $this->httpPost("/api/auth/generations/{$logId}/request-approval", $fields ? ['json' => $fields] : []);
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function requestApprovalBulk(array $fields): array
    {
        return $this->httpPost('/api/auth/generations/request-approval-bulk', ['json' => $fields]);
    }

    /** @return array<string,mixed> */
    public function pinned(): array
    {
        return $this->httpGet('/api/auth/pinned-generations');
    }

    /** @return array<string,mixed> */
    public function pin(int $logId): array
    {
        return $this->httpPost('/api/auth/pinned-generations', ['json' => ['log_id' => $logId]]);
    }

    /** @return array<string,mixed> */
    public function unpin(int $logId): array
    {
        return $this->httpDelete("/api/auth/pinned-generations/{$logId}");
    }
}
