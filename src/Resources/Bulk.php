<?php

declare(strict_types=1);

namespace HeroShots\Resources;

use HeroShots\Errors\ApiException;

/**
 * Bulk image generation (API key or portal JWT, /api/v1/bulk).
 *
 * Flow: create() -> uploadImages() (one item per product photo) -> start() ->
 * wait() -> downloadGenerated() (zip) or per-item downloadItem().
 */
class Bulk extends AbstractResource
{
    /** Terminal bulk statuses (a failed bulk is returned by wait(), not thrown). */
    public const DONE_STATUSES = ['completed', 'partially_completed', 'failed'];

    /** @return mixed GET /api/v1/bulk -> your bulks */
    public function list(): mixed
    {
        return $this->httpGet('/api/v1/bulk');
    }

    /**
     * POST /api/v1/bulk -> new draft bulk.
     *
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    public function create(?string $prompt = null, ?int $avatarId = null, array $fields = []): array
    {
        $body = self::compact(array_merge(['prompt' => $prompt, 'avatar_id' => $avatarId], $fields));
        return $this->httpPost('/api/v1/bulk', ['json' => $body]);
    }

    /** @return array<string,mixed> GET /api/v1/bulk/<id> -> status, progress and items */
    public function get(int|string $bulkId): array
    {
        return $this->httpGet("/api/v1/bulk/{$bulkId}");
    }

    /**
     * PATCH /api/v1/bulk/<id> -> prompt, avatar_id, view_type, avatar_image_set...
     *
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    public function update(int|string $bulkId, array $fields = []): array
    {
        return $this->httpPatch("/api/v1/bulk/{$bulkId}", ['json' => $fields]);
    }

    /** @return mixed DELETE /api/v1/bulk/<id> */
    public function delete(int|string $bulkId): mixed
    {
        return $this->httpDelete("/api/v1/bulk/{$bulkId}");
    }

    /**
     * POST /api/v1/bulk/<id>/images (multipart, one "files" part per image) -> {uploaded, items}.
     *
     * @param iterable<string|resource> $files paths, strings of bytes or stream resources
     * @return array<string,mixed>
     */
    public function uploadImages(int|string $bulkId, iterable $files): array
    {
        $parts = [];
        foreach ($files as $file) {
            $parts[] = $this->filePart($file, 'files', 'image');
        }
        return $this->httpPost("/api/v1/bulk/{$bulkId}/images", ['multipart' => $parts]);
    }

    /** @return mixed DELETE /api/v1/bulk/<id>/items/<item_id> */
    public function removeItem(int|string $bulkId, int|string $itemId): mixed
    {
        return $this->httpDelete("/api/v1/bulk/{$bulkId}/items/{$itemId}");
    }

    /**
     * POST /api/v1/bulk/<id>/items/<item_id>/replace-product (multipart "file").
     *
     * @param string|resource $file
     * @return array<string,mixed>
     */
    public function replaceProduct(int|string $bulkId, int|string $itemId, mixed $file): array
    {
        return $this->httpPost("/api/v1/bulk/{$bulkId}/items/{$itemId}/replace-product", ['multipart' => $this->multipart($file)]);
    }

    /** @return array<string,mixed> POST /api/v1/bulk/<id>/start -> begins generation for every item */
    public function start(int|string $bulkId): array
    {
        return $this->httpPost("/api/v1/bulk/{$bulkId}/start");
    }

    /** @return array<string,mixed> POST /api/v1/bulk/<id>/items/<item_id>/cancel */
    public function cancelItem(int|string $bulkId, int|string $itemId): array
    {
        return $this->httpPost("/api/v1/bulk/{$bulkId}/items/{$itemId}/cancel");
    }

    /** @return array<string,mixed> POST /api/v1/bulk/<id>/items/<item_id>/regenerate */
    public function regenerateItem(
        int|string $bulkId,
        int|string $itemId,
        ?string $promptOverride = null,
        ?bool $verifyUntilOk = null,
        ?bool $refineLast = null
    ): array {
        $body = self::compact([
            'prompt_override' => $promptOverride,
            'verify_until_ok' => $verifyUntilOk,
            'refine_last' => $refineLast,
        ]);
        return $this->httpPost("/api/v1/bulk/{$bulkId}/items/{$itemId}/regenerate", ['json' => $body]);
    }

    /** @return array<string,mixed> POST /api/v1/bulk/<id>/regenerate-all */
    public function regenerateAll(int|string $bulkId): array
    {
        return $this->httpPost("/api/v1/bulk/{$bulkId}/regenerate-all");
    }

    /** @return array<string,mixed> GET /api/v1/bulk/<id>/items/previews -> base64 previews per item */
    public function previews(int|string $bulkId): array
    {
        return $this->httpGet("/api/v1/bulk/{$bulkId}/items/previews");
    }

    /** @return array<string,mixed> GET /api/v1/bulk/<id>/items/<item_id>/attempts */
    public function attempts(int|string $bulkId, int|string $itemId): array
    {
        return $this->httpGet("/api/v1/bulk/{$bulkId}/items/{$itemId}/attempts");
    }

    /** @return array<string,mixed> POST /api/v1/bulk/<id>/items/<item_id>/attempts/select -> make it the official result */
    public function selectAttempt(int|string $bulkId, int|string $itemId, int $attemptIndex): array
    {
        return $this->httpPost("/api/v1/bulk/{$bulkId}/items/{$itemId}/attempts/select", ['json' => ['attempt_index' => $attemptIndex]]);
    }

    /** GET /api/v1/bulk/<id>/items/<item_id>/download-product -> uploaded product image (bytes or saved path). */
    public function downloadProduct(int|string $bulkId, int|string $itemId, ?string $dest = null): string
    {
        return $this->client->download("/api/v1/bulk/{$bulkId}/items/{$itemId}/download-product", $dest);
    }

    /** GET /api/v1/bulk/<id>/items/<item_id>/download -> generated image (bytes or saved path). */
    public function downloadItem(int|string $bulkId, int|string $itemId, ?string $dest = null): string
    {
        return $this->client->download("/api/v1/bulk/{$bulkId}/items/{$itemId}/download", $dest);
    }

    /**
     * GET /api/v1/bulk/<id>/download-generated -> zip of generated images (bytes or saved path).
     *
     * @param iterable<int|string>|null $itemIds only these items (sent as a comma list)
     */
    public function downloadGenerated(int|string $bulkId, ?string $dest = null, ?iterable $itemIds = null): string
    {
        $ids = [];
        foreach ($itemIds ?? [] as $id) {
            $ids[] = (string) $id;
        }
        $query = $ids ? ['item_ids' => implode(',', $ids)] : [];
        return $this->client->download("/api/v1/bulk/{$bulkId}/download-generated", $dest, $query);
    }

    /**
     * Poll until the bulk is completed, partially_completed or failed.
     *
     * Unlike single jobs, a failed bulk is returned (not thrown): check status
     * and the per-item results.
     *
     * @param callable(array<string,mixed>):void|null $onProgress
     * @return array<string,mixed>
     */
    public function wait(int|string $bulkId, float $interval = 10.0, float $timeout = 3600.0, ?callable $onProgress = null): array
    {
        $deadline = microtime(true) + $timeout;
        while (true) {
            $bulk = $this->get($bulkId);
            if ($onProgress !== null) {
                $onProgress($bulk);
            }
            $status = strtolower((string) ($bulk['status'] ?? ''));
            if (in_array($status, self::DONE_STATUSES, true)) {
                return $bulk;
            }
            if (microtime(true) >= $deadline) {
                throw new ApiException("bulk did not finish within {$timeout}s (last status={$status})", 'timeout');
            }
            if ($interval > 0) {
                usleep((int) ($interval * 1_000_000));
            }
        }
    }
}
