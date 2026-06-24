<?php

declare(strict_types=1);

namespace HeroShots\Resources;

use HeroShots\Polling;

/** Long / multi-segment video pipeline (portal JWT, /api/auth/long-video). */
class LongVideo extends AbstractResource
{
    /**
     * @param array<string,mixed>|null $storyboard
     * @param array<string,mixed> $payload
     * @return array<string,mixed> POST /api/auth/long-video/start -> {job_id, ...}
     */
    public function start(?string $imageUrl = null, ?string $customPrompt = null, ?array $storyboard = null, string $ratio = '16:9', string $resolution = '1080p', array $payload = []): array
    {
        $body = array_merge(['ratio' => $ratio, 'resolution' => $resolution], $payload);
        if ($imageUrl !== null) {
            $body['image_url'] = $this->resolveImage($imageUrl, 'products');
        }
        if ($customPrompt !== null) {
            $body['custom_prompt'] = $customPrompt;
        }
        if ($storyboard !== null) {
            $body['storyboard'] = $storyboard;
        }
        return $this->httpPost('/api/auth/long-video/start', ['json' => $body]);
    }

    /** @return array<string,mixed> GET /api/auth/long-video/status/<job_id> */
    public function status(int $jobId): array
    {
        return $this->httpGet("/api/auth/long-video/status/{$jobId}");
    }

    /** @return array<string,mixed> GET /api/auth/long-video/view/<token> */
    public function view(string $token): array
    {
        return $this->httpGet("/api/auth/long-video/view/{$token}");
    }

    /**
     * @param callable(array<string,mixed>):void|null $onProgress
     * @return array<string,mixed> -> payload with final_video_url
     */
    public function wait(int $jobId, float $interval = 5.0, float $timeout = 1800.0, ?callable $onProgress = null): array
    {
        return Polling::wait(
            fn () => $this->status($jobId),
            Polling::STORYBOARD_OK,
            Polling::STORYBOARD_FAIL,
            'status',
            $interval,
            $timeout,
            $onProgress
        );
    }
}
