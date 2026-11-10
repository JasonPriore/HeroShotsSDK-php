<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/** Library: collections of generations (API key or portal JWT, /api/v1/collections). */
class Collections extends AbstractResource
{
    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed> GET /api/v1/collections
     */
    public function list(array $query = []): array
    {
        $query = self::compact($query);
        return $this->httpGet('/api/v1/collections', $query ? ['query' => $query] : []);
    }

    /**
     * POST /api/v1/collections (JSON, or multipart when a $cover file is given).
     *
     * @param string|resource|null $cover path, string of bytes or stream resource
     * @return array<string,mixed>
     */
    public function create(string $name, ?string $description = null, mixed $cover = null): array
    {
        $fields = self::compact(['name' => $name, 'description' => $description]);
        if ($cover !== null) {
            return $this->httpPost('/api/v1/collections', ['multipart' => $this->multipart($cover, $fields, 'cover')]);
        }
        return $this->httpPost('/api/v1/collections', ['json' => $fields]);
    }

    /** @return array<string,mixed> GET /api/v1/collections/<id> */
    public function get(int|string $collectionId): array
    {
        return $this->httpGet("/api/v1/collections/{$collectionId}");
    }

    /**
     * PATCH /api/v1/collections/<id> (multipart when a $cover file is given).
     *
     * @param string|resource|null $cover
     * @return array<string,mixed>
     */
    public function update(int|string $collectionId, ?string $name = null, ?string $description = null, mixed $cover = null): array
    {
        $fields = self::compact(['name' => $name, 'description' => $description]);
        $path = "/api/v1/collections/{$collectionId}";
        if ($cover !== null) {
            return $this->httpPatch($path, ['multipart' => $this->multipart($cover, $fields, 'cover')]);
        }
        return $this->httpPatch($path, ['json' => $fields]);
    }

    /** @return mixed DELETE /api/v1/collections/<id> */
    public function delete(int|string $collectionId): mixed
    {
        return $this->httpDelete("/api/v1/collections/{$collectionId}");
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed> GET /api/v1/collections/<id>/generations
     */
    public function generations(int|string $collectionId, array $query = []): array
    {
        $query = self::compact($query);
        return $this->httpGet("/api/v1/collections/{$collectionId}/generations", $query ? ['query' => $query] : []);
    }

    /** @return array<string,mixed> POST /api/v1/collections/<id>/generations */
    public function addGeneration(int|string $collectionId, int|string $logId): array
    {
        return $this->httpPost("/api/v1/collections/{$collectionId}/generations", ['json' => ['log_id' => (int) $logId]]);
    }

    /** @return mixed DELETE /api/v1/collections/<id>/generations/<log_id> */
    public function removeGeneration(int|string $collectionId, int|string $logId): mixed
    {
        return $this->httpDelete("/api/v1/collections/{$collectionId}/generations/{$logId}");
    }

    /** @return array<string,mixed> PATCH /api/v1/collections/<id>/generations/<log_id>/move */
    public function moveGeneration(int|string $collectionId, int|string $logId, int|string $targetCollectionId): array
    {
        return $this->httpPatch(
            "/api/v1/collections/{$collectionId}/generations/{$logId}/move",
            ['json' => ['target_collection_id' => (int) $targetCollectionId]]
        );
    }

    /**
     * @param array<string,mixed> $query limit, offset
     * @return array<string,mixed> GET /api/v1/collections/<id>/available-generations
     */
    public function availableGenerations(int|string $collectionId, array $query = []): array
    {
        $query = self::compact($query);
        return $this->httpGet("/api/v1/collections/{$collectionId}/available-generations", $query ? ['query' => $query] : []);
    }
}
