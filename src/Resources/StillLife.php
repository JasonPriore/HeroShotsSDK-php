<?php

declare(strict_types=1);

namespace HeroShots\Resources;

use HeroShots\Polling;

/** Still-life product video (API key or portal JWT, /api/v1/stilllife-video). */
class StillLife extends AbstractResource
{
    /**
     * POST /api/v1/stilllife-video/start -> {task_id, status, ...}.
     *
     * $productUrl and $backgroundUrl are required (local paths auto-upload with a
     * portal JWT).
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function start(
        string $productUrl,
        string $backgroundUrl,
        ?string $customPrompt = null,
        string $ratio = '16:9',
        string $resolution = '1080p',
        int $durationSeconds = 5,
        array $payload = [],
        ?string $detailImageUrl = null,
        ?string $referenceVideoUrl = null
    ): array {
        $body = array_merge([
            'product_url' => $this->resolveImage($productUrl, 'products'),
            'background_url' => $this->resolveImage($backgroundUrl, 'background'),
            'ratio' => $ratio,
            'resolution' => $resolution,
            'duration_seconds' => $durationSeconds,
        ], $payload);
        $body = array_merge($body, self::compact([
            'custom_prompt' => $customPrompt,
            'detail_image_url' => $this->resolveImage($detailImageUrl, 'products'),
            'reference_video_url' => $referenceVideoUrl,
        ]));
        return $this->httpPost('/api/v1/stilllife-video/start', ['json' => $body]);
    }

    /** @return array<string,mixed> GET /api/v1/stilllife-video/status/<task_id> */
    public function status(int|string $taskId): array
    {
        return $this->httpGet("/api/v1/stilllife-video/status/{$taskId}");
    }

    /**
     * Poll until terminal -> payload with the finished video URL.
     *
     * @param callable(array<string,mixed>):void|null $onProgress
     * @return array<string,mixed>
     */
    public function wait(int|string $taskId, float $interval = 5.0, float $timeout = 900.0, ?callable $onProgress = null): array
    {
        return Polling::wait(
            fn () => $this->status($taskId),
            Polling::MEDIA_OK,
            Polling::MEDIA_FAIL,
            'status',
            $interval,
            $timeout,
            $onProgress
        );
    }

    // -- background edits (portal JWT only) ------------------------------------

    /** @param array<string,mixed> $payload @return array<string,mixed> POST /api/auth/stilllife-video/<task_id>/preview-background */
    public function previewBackground(int|string $taskId, array $payload = []): array
    {
        return $this->httpPost("/api/auth/stilllife-video/{$taskId}/preview-background", ['json' => $payload]);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> POST /api/auth/stilllife-video/<task_id>/change-background */
    public function changeBackground(int|string $taskId, array $payload = []): array
    {
        return $this->httpPost("/api/auth/stilllife-video/{$taskId}/change-background", ['json' => $payload]);
    }

    /** @return array<string,mixed> GET /api/auth/stilllife-video/<task_id>/change-background/status */
    public function changeBackgroundStatus(int|string $taskId): array
    {
        return $this->httpGet("/api/auth/stilllife-video/{$taskId}/change-background/status");
    }
}
