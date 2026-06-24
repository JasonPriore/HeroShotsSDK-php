<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/**
 * Avatars (JWT-or-API-key, /api/v1/avatar).
 *
 * Read-only listing of your own + default avatars, plus on-demand generation.
 * (Editing/deleting avatars is intentionally not exposed.)
 */
class Avatars extends AbstractResource
{
    /**
     * List available avatars (your own and the default library).
     *
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public function list(array $query = []): array
    {
        return $this->httpGet('/api/v1/avatar/list', $query ? ['query' => $query] : []);
    }

    /** @return array<string,mixed> Fetch a single avatar by id. */
    public function get(int $avatarId): array
    {
        return $this->httpGet("/api/v1/avatar/{$avatarId}");
    }

    /**
     * Request generation of a new avatar.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function generate(array $payload = []): array
    {
        return $this->httpPost('/api/v1/avatar/generate', ['json' => $payload]);
    }
}
