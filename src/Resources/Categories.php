<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/** Library: product categories (API key or portal JWT, /api/v1/categories). */
class Categories extends AbstractResource
{
    /** @return mixed GET /api/v1/categories -> active categories */
    public function list(): mixed
    {
        return $this->httpGet('/api/v1/categories');
    }

    /** @return array<string,mixed> GET /api/v1/categories/selected -> categories chosen for this account */
    public function selected(): array
    {
        return $this->httpGet('/api/v1/categories/selected');
    }

    /**
     * POST /api/v1/categories/selected.
     *
     * @param array<int,int|string> $categoryIds
     * @return array<string,mixed>
     */
    public function setSelected(array $categoryIds): array
    {
        $ids = array_values(array_map(static fn ($c) => (int) $c, $categoryIds));
        return $this->httpPost('/api/v1/categories/selected', ['json' => ['category_ids' => $ids]]);
    }
}
