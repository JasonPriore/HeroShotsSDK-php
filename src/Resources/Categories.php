<?php

declare(strict_types=1);

namespace HeroShots\Resources;

class Categories extends AbstractResource
{
    private string $base = '/api/v1/categories';

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function list(array $query = []): array
    {
        return $this->httpGet($this->base, $query ? ['query' => $query] : []);
    }

    /** @return array<string,mixed> */
    public function selected(): array
    {
        return $this->httpGet("{$this->base}/selected");
    }

    /** @param int[] $categoryIds @return array<string,mixed> */
    public function setSelected(array $categoryIds): array
    {
        return $this->httpPost("{$this->base}/selected", ['json' => ['category_ids' => $categoryIds]]);
    }
}
