<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/** Dreamcast video tasks (JWT-or-API-key, /api/v1/dreamcast). Not for model accounts. */
class Dreamcast extends AbstractResource
{
    private string $base = '/api/v1/dreamcast/tasks';

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

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function createFromUrl(array $fields): array
    {
        return $this->httpPost("{$this->base}/from-url", ['json' => $fields]);
    }

    /** @return array<string,mixed> */
    public function get(int $taskDbId): array
    {
        return $this->httpGet("{$this->base}/{$taskDbId}");
    }

    /** @return array<string,mixed> */
    public function delete(int $taskDbId): array
    {
        return $this->httpDelete("{$this->base}/{$taskDbId}");
    }
}
