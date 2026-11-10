<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/** Library: saved background images (API key or portal JWT, /api/v1/backgrounds). */
class Backgrounds extends AbstractResource
{
    /**
     * @param array<string,mixed> $query limit, offset, q
     * @return array<string,mixed> GET /api/v1/backgrounds
     */
    public function list(array $query = []): array
    {
        $query = self::compact($query);
        return $this->httpGet('/api/v1/backgrounds', $query ? ['query' => $query] : []);
    }

    /**
     * POST /api/v1/backgrounds -> save a background from a $file (multipart) or an $imageUrl (JSON).
     *
     * @param string|resource|null $file path, string of bytes or stream resource
     * @return array<string,mixed>
     */
    public function create(
        mixed $file = null,
        ?string $imageUrl = null,
        ?string $name = null,
        ?string $source = null,
        ?string $prompt = null
    ): array {
        $fields = self::compact(['name' => $name, 'source' => $source, 'prompt' => $prompt, 'image_url' => $imageUrl]);
        if ($file !== null) {
            return $this->httpPost('/api/v1/backgrounds', ['multipart' => $this->multipart($file, $fields)]);
        }
        return $this->httpPost('/api/v1/backgrounds', ['json' => $fields]);
    }

    /** @return array<string,mixed> GET /api/v1/backgrounds/<id> */
    public function get(int|string $backgroundId): array
    {
        return $this->httpGet("/api/v1/backgrounds/{$backgroundId}");
    }

    /**
     * PATCH /api/v1/backgrounds/<id> (e.g. name).
     *
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    public function update(int|string $backgroundId, array $fields = []): array
    {
        return $this->httpPatch("/api/v1/backgrounds/{$backgroundId}", ['json' => $fields]);
    }

    /** @return mixed DELETE /api/v1/backgrounds/<id> */
    public function delete(int|string $backgroundId): mixed
    {
        return $this->httpDelete("/api/v1/backgrounds/{$backgroundId}");
    }
}
