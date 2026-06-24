<?php

declare(strict_types=1);

namespace HeroShots\Resources;

class Collections extends AbstractResource
{
    private string $base = '/api/v1/collections';

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
    public function get(int $id): array
    {
        return $this->httpGet("{$this->base}/{$id}");
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function update(int $id, array $fields): array
    {
        return $this->httpPatch("{$this->base}/{$id}", ['json' => $fields]);
    }

    /** @return array<string,mixed> */
    public function delete(int $id): array
    {
        return $this->httpDelete("{$this->base}/{$id}");
    }

    /** @return array<string,mixed> */
    public function generations(int $id): array
    {
        return $this->httpGet("{$this->base}/{$id}/generations");
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function addGeneration(int $id, array $fields): array
    {
        return $this->httpPost("{$this->base}/{$id}/generations", ['json' => $fields]);
    }

    /** @return array<string,mixed> */
    public function removeGeneration(int $id, int $logId): array
    {
        return $this->httpDelete("{$this->base}/{$id}/generations/{$logId}");
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function moveGeneration(int $id, int $logId, array $fields): array
    {
        return $this->httpPatch("{$this->base}/{$id}/generations/{$logId}/move", ['json' => $fields]);
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function availableGenerations(int $id, array $query = []): array
    {
        return $this->httpGet("{$this->base}/{$id}/available-generations", $query ? ['query' => $query] : []);
    }
}
