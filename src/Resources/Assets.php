<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/** Library: product assets (API key or portal JWT, /api/v1/assets). */
class Assets extends AbstractResource
{
    /** @return array<string,mixed> GET /api/v1/assets */
    public function list(int|string|null $categoryId = null): array
    {
        return $this->httpGet('/api/v1/assets', $categoryId !== null ? ['query' => ['category_id' => $categoryId]] : []);
    }

    /**
     * POST /api/v1/assets (multipart) -> new product asset.
     *
     * @param string|resource $primaryImage path, string of bytes or stream resource
     * @return array<string,mixed>
     */
    public function create(
        string $name,
        mixed $primaryImage,
        int|string|null $categoryId = null,
        ?string $productDescription = null,
        ?string $productDetails = null
    ): array {
        $fields = self::compact([
            'name' => $name,
            'category_id' => $categoryId,
            'product_description' => $productDescription,
            'product_details' => $productDetails,
        ]);
        return $this->httpPost('/api/v1/assets', ['multipart' => $this->multipart($primaryImage, $fields, 'primary_image')]);
    }

    /** @return array<string,mixed> POST /api/v1/assets/import-amazon -> asset draft from an Amazon product URL */
    public function importAmazon(string $url, ?string $languageCode = null): array
    {
        return $this->httpPost('/api/v1/assets/import-amazon', ['json' => self::compact(['url' => $url, 'language_code' => $languageCode])]);
    }

    /** @return array<string,mixed> GET /api/v1/assets/<id> */
    public function get(int|string $assetId): array
    {
        return $this->httpGet("/api/v1/assets/{$assetId}");
    }

    /**
     * PATCH /api/v1/assets/<id> -> name, category_id, product_description...
     *
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    public function update(int|string $assetId, array $fields = []): array
    {
        return $this->httpPatch("/api/v1/assets/{$assetId}", ['json' => $fields]);
    }

    /** @return mixed DELETE /api/v1/assets/<id> */
    public function delete(int|string $assetId): mixed
    {
        return $this->httpDelete("/api/v1/assets/{$assetId}");
    }

    /**
     * POST /api/v1/assets/<id>/images (multipart "file") -> extra product photo.
     *
     * @param string|resource $file
     * @return array<string,mixed>
     */
    public function addImage(int|string $assetId, mixed $file): array
    {
        return $this->httpPost("/api/v1/assets/{$assetId}/images", ['multipart' => $this->multipart($file)]);
    }

    /** @return mixed DELETE /api/v1/assets/<id>/images/<image_id> */
    public function removeImage(int|string $assetId, int|string $imageId): mixed
    {
        return $this->httpDelete("/api/v1/assets/{$assetId}/images/{$imageId}");
    }
}
