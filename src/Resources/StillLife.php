<?php

declare(strict_types=1);

namespace HeroShots\Resources;

use HeroShots\Polling;

/** Still-life product video (portal JWT, /api/auth/stilllife-video). */
class StillLife extends AbstractResource
{
    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed> POST /api/auth/stilllife-video/start
     */
    public function start(string $productUrl, string $backgroundUrl, ?string $customPrompt = null, string $ratio = '16:9', string $resolution = '1080p', int $durationSeconds = 5, array $payload = []): array
    {
        $body = array_merge([
            'product_url' => $this->resolveImage($productUrl, 'products'),
            'background_url' => $this->resolveImage($backgroundUrl, 'background'),
            'ratio' => $ratio,
            'resolution' => $resolution,
            'duration_seconds' => $durationSeconds,
        ], $payload);
        if ($customPrompt !== null) {
            $body['custom_prompt'] = $customPrompt;
        }
        return $this->httpPost('/api/auth/stilllife-video/start', ['json' => $body]);
    }

    /** @return array<string,mixed> GET /api/auth/stilllife-video/status/<task_id> */
    public function status(string $taskId): array
    {
        return $this->httpGet("/api/auth/stilllife-video/status/{$taskId}");
    }

    /**
     * @param callable(array<string,mixed>):void|null $onProgress
     * @return array<string,mixed>
     */
    public function wait(string $taskId, float $interval = 5.0, float $timeout = 900.0, ?callable $onProgress = null): array
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

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function previewBackground(string $taskId, array $payload = []): array
    {
        return $this->httpPost("/api/auth/stilllife-video/{$taskId}/preview-background", ['json' => $payload]);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function changeBackground(string $taskId, array $payload = []): array
    {
        return $this->httpPost("/api/auth/stilllife-video/{$taskId}/change-background", ['json' => $payload]);
    }

    /** @return array<string,mixed> */
    public function changeBackgroundStatus(string $taskId): array
    {
        return $this->httpGet("/api/auth/stilllife-video/{$taskId}/change-background/status");
    }
}
