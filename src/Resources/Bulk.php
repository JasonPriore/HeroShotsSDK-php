<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/** Bulk jobs (JWT-or-API-key, /api/v1/bulk). Unavailable to model accounts. */
class Bulk extends AbstractResource
{
    private string $base = '/api/v1/bulk';

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function list(array $query = []): array
    {
        return $this->httpGet($this->base, $query ? ['query' => $query] : []);
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function create(array $fields): array
    {
        return $this->httpPost($this->base, ['json' => $fields]);
    }

    /** @return array<string,mixed> */
    public function get(int $bulkId): array
    {
        return $this->httpGet("{$this->base}/{$bulkId}");
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function update(int $bulkId, array $fields): array
    {
        return $this->httpPatch("{$this->base}/{$bulkId}", ['json' => $fields]);
    }

    /** @return array<string,mixed> */
    public function delete(int $bulkId): array
    {
        return $this->httpDelete("{$this->base}/{$bulkId}");
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function start(int $bulkId, array $fields = []): array
    {
        return $this->httpPost("{$this->base}/{$bulkId}/start", $fields ? ['json' => $fields] : []);
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function addImages(int $bulkId, array $fields): array
    {
        return $this->httpPost("{$this->base}/{$bulkId}/images", ['json' => $fields]);
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function replaceProduct(int $bulkId, int $itemId, array $fields): array
    {
        return $this->httpPost("{$this->base}/{$bulkId}/items/{$itemId}/replace-product", ['json' => $fields]);
    }

    /** @return array<string,mixed> */
    public function deleteItem(int $bulkId, int $itemId): array
    {
        return $this->httpDelete("{$this->base}/{$bulkId}/items/{$itemId}");
    }

    /** @return array<string,mixed> */
    public function cancelItem(int $bulkId, int $itemId): array
    {
        return $this->httpPost("{$this->base}/{$bulkId}/items/{$itemId}/cancel");
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function regenerateItem(int $bulkId, int $itemId, array $fields = []): array
    {
        return $this->httpPost("{$this->base}/{$bulkId}/items/{$itemId}/regenerate", $fields ? ['json' => $fields] : []);
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function regenerateAll(int $bulkId, array $fields = []): array
    {
        return $this->httpPost("{$this->base}/{$bulkId}/regenerate-all", $fields ? ['json' => $fields] : []);
    }

    /** @return array<string,mixed> */
    public function itemAttempts(int $bulkId, int $itemId): array
    {
        return $this->httpGet("{$this->base}/{$bulkId}/items/{$itemId}/attempts");
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function selectAttempt(int $bulkId, int $itemId, array $fields): array
    {
        return $this->httpPost("{$this->base}/{$bulkId}/items/{$itemId}/attempts/select", ['json' => $fields]);
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function previews(int $bulkId, array $query = []): array
    {
        return $this->httpGet("{$this->base}/{$bulkId}/items/previews", $query ? ['query' => $query] : []);
    }

    /** @return array<string,mixed> */
    public function downloadItem(int $bulkId, int $itemId): array
    {
        return $this->httpGet("{$this->base}/{$bulkId}/items/{$itemId}/download");
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function downloadGenerated(int $bulkId, array $query = []): array
    {
        return $this->httpGet("{$this->base}/{$bulkId}/download-generated", $query ? ['query' => $query] : []);
    }
}
