<?php

declare(strict_types=1);

namespace HeroShots\Resources;

use HeroShots\Polling;

/** Short video generation (API key or portal JWT, /api/v1/video). */
class Videos extends AbstractResource
{
    /**
     * POST /api/v1/video/generate -> {video_id, status, ...} (async).
     *
     * Common fields: duration_seconds (15 / 30 / longer in 15 s blocks),
     * custom_prompt, product_image_url, subject_image_url or avatar_id, script,
     * tts_voice_id, language_code, ratio, resolution, reference_video_url, usage
     * rights. Local file paths in $imageUrl, product_image_url and
     * subject_image_url are auto-uploaded (portal JWT only).
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function generate(?string $imageUrl = null, array $payload = []): array
    {
        $body = $payload;
        if ($imageUrl !== null) {
            $body['image_url'] = $this->resolveImage($imageUrl, 'products');
        }
        foreach (['product_image_url' => 'products', 'subject_image_url' => 'subjects'] as $key => $folder) {
            if (array_key_exists($key, $body)) {
                $body[$key] = $this->resolveImage($body[$key], $folder);
            }
        }
        return $this->httpPost('/api/v1/video/generate', ['json' => $body]);
    }

    /** @return array<string,mixed> GET /api/v1/video/status/<task_id> -> {status, video_url, ...} */
    public function status(string $taskId, ?string $provider = null): array
    {
        $options = $provider ? ['query' => ['provider' => $provider]] : [];
        return $this->httpGet("/api/v1/video/status/{$taskId}", $options);
    }

    /**
     * Poll video status until succeeded/failed -> payload with video_url.
     *
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

    /** GET /api/v1/video/<log_id>/download -> MP4 bytes, or the saved path when $dest is given. */
    public function download(int|string $logId, ?string $dest = null): string
    {
        return $this->client->download("/api/v1/video/{$logId}/download", $dest);
    }
}
