<?php

declare(strict_types=1);

namespace HeroShots\Resources;

use HeroShots\Polling;

/** Image generation (JWT-or-API-key on /api/v1, portal candidates flow). */
class Images extends AbstractResource
{
    /** @param array<string,mixed> $payload @return array<string,mixed> POST /api/v1/image/generate */
    public function generate(string $productImageUrl, ?string $subjectImageUrl = null, array $payload = []): array
    {
        $body = $payload;
        $body['product_image_url'] = $this->resolveImage($productImageUrl, 'products');
        if ($subjectImageUrl !== null) {
            $body['subject_image_url'] = $this->resolveImage($subjectImageUrl, 'subjects');
        }
        return $this->httpPost('/api/v1/image/generate', ['json' => $body]);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> POST /api/auth/image/generate/candidates */
    public function candidates(string $productImageUrl, ?string $subjectImageUrl = null, array $payload = []): array
    {
        $body = $payload;
        $body['product_image_url'] = $this->resolveImage($productImageUrl, 'products');
        if ($subjectImageUrl !== null) {
            $body['subject_image_url'] = $this->resolveImage($subjectImageUrl, 'subjects');
        }
        return $this->httpPost('/api/auth/image/generate/candidates', ['json' => $body]);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function selectCandidate(string $token, array $payload = []): array
    {
        return $this->httpPost("/api/auth/image/generate/candidates/{$token}/select", ['json' => $payload]);
    }

    /** @return array<string,mixed> GET /api/v1/image/status/<generation_id> */
    public function status(string $generationId): array
    {
        return $this->httpGet("/api/v1/image/status/{$generationId}");
    }

    /** @return array<string,mixed> GET /api/v1/image/<log_id>/download */
    public function download(string $logId): array
    {
        return $this->httpGet("/api/v1/image/{$logId}/download");
    }

    /**
     * @param callable(array<string,mixed>):void|null $onProgress
     * @return array<string,mixed>
     */
    public function wait(string $generationId, float $interval = 3.0, float $timeout = 600.0, ?callable $onProgress = null): array
    {
        return Polling::wait(
            fn () => $this->status($generationId),
            Polling::MEDIA_OK,
            Polling::MEDIA_FAIL,
            'status',
            $interval,
            $timeout,
            $onProgress
        );
    }
}
