<?php

declare(strict_types=1);

namespace HeroShots\Resources;

use HeroShots\Errors\ApiException;
use HeroShots\Errors\ProviderException;
use HeroShots\Polling;

/** Long / multi-segment video (API key or portal JWT, /api/v1/long-video). */
class LongVideo extends AbstractResource
{
    /**
     * The job pauses here when every segment is rendered: the clips are ready
     * (segments[].video_url) and the final cut is made by export().
     */
    public const SEGMENTS_READY = 'segments_ready';

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

    /**
     * POST /api/auth/long-video/<job_id>/export -> joins the clips into final_video_url.
     *
     * Portal JWT only. Needs every segment done (segments_ready or done).
     * $segmentOrder (1-based indexes) reorders the clips. Not billed.
     *
     * @param int[]|null $segmentOrder
     * @param array<string,mixed>|null $timeline
     * @return array<string,mixed>
     */
    public function export(int|string $jobId, ?array $segmentOrder = null, ?array $timeline = null): array
    {
        $body = self::compact(['segment_order' => $segmentOrder, 'timeline' => $timeline]);
        return $this->httpPost("/api/auth/long-video/{$jobId}/export", ['json' => $body]);
    }

    /** @return array<string,mixed> GET /api/auth/long-video/view/<token> (share link payload, portal JWT) */
    public function view(string $token): array
    {
        return $this->httpGet("/api/auth/long-video/view/{$token}");
    }

    /**
     * Poll until the clips are ready (segments_ready), done or failed.
     *
     * At segments_ready every segments[i].video_url is set but final_video_url is
     * still empty: call export() (or use render()) to get the joined video.
     * Dubbed jobs go straight to done.
     *
     * @param callable(array<string,mixed>):void|null $onProgress
     * @return array<string,mixed>
     */
    public function wait(int|string $jobId, float $interval = 5.0, float $timeout = 1800.0, ?callable $onProgress = null): array
    {
        return Polling::wait(
            fn () => $this->status($jobId),
            [...Polling::STORYBOARD_OK, self::SEGMENTS_READY],
            Polling::STORYBOARD_FAIL,
            'status',
            $interval,
            $timeout,
            $onProgress
        );
    }

    /**
     * Wait for the clips, export them and wait for final_video_url (portal JWT).
     *
     * @param int[]|null $segmentOrder
     * @param callable(array<string,mixed>):void|null $onProgress
     * @return array<string,mixed> final status payload (status "done")
     */
    public function render(
        int|string $jobId,
        ?array $segmentOrder = null,
        float $interval = 5.0,
        float $timeout = 2400.0,
        ?callable $onProgress = null
    ): array {
        $deadline = microtime(true) + $timeout;
        $job = $this->wait($jobId, $interval, $timeout, $onProgress);
        if (strtolower((string) ($job['status'] ?? '')) === 'done' && !empty($job['final_video_url'])) {
            return $job;
        }
        $this->export($jobId, $segmentOrder);
        while (true) {
            $job = $this->status($jobId);
            if ($onProgress !== null) {
                $onProgress($job);
            }
            $status = strtolower((string) ($job['status'] ?? ''));
            if ($status === 'done' && !empty($job['final_video_url'])) {
                return $job;
            }
            if (in_array($status, Polling::STORYBOARD_FAIL, true)) {
                $err = $job['error'] ?? null;
                throw new ProviderException(is_string($err) && $err !== '' ? $err : "long video {$status}", 'provider_error');
            }
            if (microtime(true) >= $deadline) {
                throw new ApiException("long video export did not finish within {$timeout}s (last status={$status})", 'timeout');
            }
            if ($interval > 0) {
                usleep((int) ($interval * 1_000_000));
            }
        }
    }
}
