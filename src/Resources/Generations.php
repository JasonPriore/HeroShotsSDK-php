<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/** Generation history (API key or portal JWT, /api/v1/generations). */
class Generations extends AbstractResource
{
    /**
     * GET /api/v1/generations -> {generations, total, page, per_page, pages}.
     *
     * Filters: type ("image" / "video"), page, per_page, date_from, date_to,
     * collection_id, generated_by_user_id. Null values are dropped.
     *
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function list(array $filters = []): array
    {
        $query = self::compact($filters);
        return $this->httpGet('/api/v1/generations', $query ? ['query' => $query] : []);
    }

    /**
     * Yield every generation across all pages.
     *
     * @param array<string,mixed> $filters
     * @return \Generator<int,array<string,mixed>>
     */
    public function iterate(array $filters = [], int $perPage = 50): \Generator
    {
        $page = 1;
        while (true) {
            $resp = $this->list(array_merge($filters, ['page' => $page, 'per_page' => $perPage]));
            foreach ($resp['generations'] ?? [] as $item) {
                yield $item;
            }
            $pages = (int) ($resp['pages'] ?? 1) ?: 1;
            if ($page >= $pages) {
                return;
            }
            $page++;
        }
    }
}
