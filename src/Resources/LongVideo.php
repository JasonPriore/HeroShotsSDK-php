<?php

declare(strict_types=1);

namespace HeroShots\Resources;

use HeroShots\Polling;

/** Long / multi-segment video (API key or portal JWT, /api/v1/long-video). */
class LongVideo extends AbstractResource
{
    /**
     * POST /api/v1/long-video/start -> {job_id, status, segment_count, ...}.
     *
     * Provide $imageUrl (local paths auto-upload with a portal JWT) or
     * image_generation_id / product_image_url + subject_image_url in $payload.
     * $durationSeconds is 30, 45 or 60. Pass a $storyboard produced by
     * $client->storyboard to drive a planned multi-segment render. Optional:
     * reference_video_url, script, tts_voice_id, language_code.
     *
     * @param array<string,mixed>|null $storyboard
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function start(
        ?string $imageUrl = null,
        ?string $customPrompt = null,
        ?array $storyboard = null,
        string $ratio = '16:9',
        string $resolution = '1080p',
        array $payload = [],
        ?int $durationSeconds = null
    ): array {
        $body = array_merge(['ratio' => $ratio, 'resolution' => $resolution], $payload);
        if ($imageUrl !== null) {
            $body['image_url'] = $this->resolveImage($imageUrl, 'products');
        }
        $body = array_merge($body, self::compact([
            'custom_prompt' => $customPrompt,
            'storyboard' => $storyboard,
            'duration_seconds' => $durationSeconds,
        ]));
        return $this->httpPost('/api/v1/long-video/start', ['json' => $body]);
    }

    /** @return array<string,mixed> GET /api/v1/long-video/status/<job_id> -> segments + final_video_url */
    public function status(int|string $jobId): array
    {
        return $this->httpGet("/api/v1/long-video/status/{$jobId}");
    }

    /**
     * POST /api/v1/long-video/<job_id>/segments/<i>/regenerate.
     *
     * $comment tells the model what to change; $cascade = true also re-renders
     * the following segments so they stay continuous.
     *
     * @return array<string,mixed>
     */
    public function regenerateSegment(int|string $jobId, int $segmentIndex, ?string $comment = null, ?bool $cascade = null): array
    {
        $body = self::compact(['comment' => $comment, 'cascade' => $cascade]);
        return $this->httpPost("/api/v1/long-video/{$jobId}/segments/{$segmentIndex}/regenerate", ['json' => $body]);
    }

    /** @return array<string,mixed> GET /api/auth/long-video/view/<token> (share link payload, portal JWT) */
    public function view(string $token): array
    {
        return $this->httpGet("/api/auth/long-video/view/{$token}");
    }

    /**
     * Poll until done/failed -> payload with final_video_url.
     *
     * @param callable(array<string,mixed>):void|null $onProgress
     * @return array<string,mixed>
     */
    public function wait(int|string $jobId, float $interval = 5.0, float $timeout = 1800.0, ?callable $onProgress = null): array
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
