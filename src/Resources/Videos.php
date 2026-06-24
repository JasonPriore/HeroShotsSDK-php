<?php

declare(strict_types=1);

namespace HeroShots\Resources;

use HeroShots\Polling;

/** Short video generation (JWT-or-API-key on /api/v1; portal status poll). */
class Videos extends AbstractResource
{
    /** @param array<string,mixed> $payload @return array<string,mixed> POST /api/v1/video/generate */
    public function generate(?string $imageUrl = null, array $payload = []): array
    {
        $body = $payload;
        if ($imageUrl !== null) {
            $body['image_url'] = $this->resolveImage($imageUrl, 'products');
        }
        return $this->httpPost('/api/v1/video/generate', ['json' => $body]);
    }

    /** @return array<string,mixed> GET /video/status/<task_id> */
    public function status(string $taskId): array
    {
        return $this->httpGet("/video/status/{$taskId}");
    }

    /**
     * @param callable(array<string,mixed>):void|null $onProgress
     * @return array<string,mixed> -> payload with video_url
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
}
