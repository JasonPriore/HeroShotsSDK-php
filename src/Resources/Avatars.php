<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/** Avatars (JWT-or-API-key, /api/v1/avatar). Unavailable to model accounts. */
class Avatars extends AbstractResource
{
    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function list(array $query = []): array
    {
        return $this->httpGet('/api/v1/avatar/list', $query ? ['query' => $query] : []);
    }

    /** @return array<string,mixed> */
    public function get(int $avatarId): array
    {
        return $this->httpGet("/api/v1/avatar/{$avatarId}");
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function update(int $avatarId, array $fields): array
    {
        return $this->httpPatch("/api/v1/avatar/{$avatarId}", ['json' => $fields]);
    }

    /** @return array<string,mixed> */
    public function delete(int $avatarId): array
    {
        return $this->httpDelete("/api/v1/avatar/{$avatarId}");
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function generate(array $payload = []): array
    {
        return $this->httpPost('/api/v1/avatar/generate', ['json' => $payload]);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function improvePrompt(array $payload = []): array
    {
        return $this->httpPost('/api/v1/avatar/improve-prompt', ['json' => $payload]);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function regenerateView(int $avatarId, array $payload = []): array
    {
        return $this->httpPost("/api/v1/avatar/{$avatarId}/regenerate-view", ['json' => $payload]);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function regenerateVariants(int $avatarId, array $payload = []): array
    {
        return $this->httpPost("/api/v1/avatar/{$avatarId}/regenerate-variants", ['json' => $payload]);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function uploadView(int $avatarId, array $payload = []): array
    {
        return $this->httpPost("/api/v1/avatar/{$avatarId}/upload-view", ['json' => $payload]);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function createFromSteps(array $payload = []): array
    {
        return $this->httpPost('/api/v1/avatar/create-from-steps', ['json' => $payload]);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function analyzeAttributes(array $payload = []): array
    {
        return $this->httpPost('/api/v1/avatar/analyze-attributes', ['json' => $payload]);
    }
}
