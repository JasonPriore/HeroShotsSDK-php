<?php

declare(strict_types=1);

namespace HeroShots\Resources;

class Assets extends AbstractResource
{
    private string $base = '/api/v1/assets';

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function list(array $query = []): array
    {
        return $this->httpGet($this->base, $query ? ['query' => $query] : []);
    }

    /** @return array<string,mixed> */
    public function get(int $id): array
    {
        return $this->httpGet("{$this->base}/{$id}");
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function create(array $fields): array
    {
        return $this->httpPost($this->base, ['json' => $fields]);
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

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function addImage(int $id, array $fields): array
    {
        return $this->httpPost("{$this->base}/{$id}/images", ['json' => $fields]);
    }

    /** @return array<string,mixed> */
    public function deleteImage(int $id, int $imageId): array
    {
        return $this->httpDelete("{$this->base}/{$id}/images/{$imageId}");
    }
}
