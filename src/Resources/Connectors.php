<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/** Integration connectors (portal JWT, /api/auth/connectors). */
class Connectors extends AbstractResource
{
    /** @return array<string,mixed> GET /api/auth/connectors/claude */
    public function claude(): array
    {
        return $this->httpGet('/api/auth/connectors/claude');
    }

    /** @param array<string,mixed> $fields @return array<string,mixed> POST /api/auth/connectors/claude/key */
    public function createClaudeKey(array $fields = []): array
    {
        return $this->httpPost('/api/auth/connectors/claude/key', $fields ? ['json' => $fields] : []);
    }
}
