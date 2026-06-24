<?php

declare(strict_types=1);

namespace HeroShots\Resources;

use HeroShots\Polling;

/**
 * StudioHero storyboard intelligence (portal JWT only):
 * brief suggestions -> candidates -> storyboard (async) -> reference frames
 * (async) -> optional revision. Feed the result into LongVideo::start().
 */
class Storyboard extends AbstractResource
{
    public const DURATIONS = [6, 8, 12, 15, 30, 45, 60];

    /**
     * @param array<string,mixed> $payload
     * @return string[] POST /video/storyboard/brief-suggestions
     */
    public function briefSuggestions(?string $productImageUrl = null, ?string $subjectImageUrl = null, array $payload = []): array
    {
        $body = $this->imageBody($productImageUrl, $subjectImageUrl, $payload);
        $resp = $this->httpPost('/video/storyboard/brief-suggestions', ['json' => $body]);
        return is_array($resp) && isset($resp['suggestions']) ? $resp['suggestions'] : $resp;
    }

    /**
     * @param array<string,mixed> $payload
     * @return mixed POST /video/storyboard/candidates
     */
    public function candidates(string $customPrompt, int $durationSeconds, ?string $productImageUrl = null, ?string $subjectImageUrl = null, bool $async = false, array $payload = []): mixed
    {
        $body = $this->imageBody($productImageUrl, $subjectImageUrl, $payload);
        $body['custom_prompt'] = $customPrompt;
        $body['duration_seconds'] = $durationSeconds;
        if ($async) {
            $body['async_storyboard'] = true;
        }
        $resp = $this->httpPost('/video/storyboard/candidates', ['json' => $body]);
        return is_array($resp) && isset($resp['candidates']) ? $resp['candidates'] : $resp;
    }

    /**
     * @param array<string,mixed>|null $selectedCandidate
     * @param array<string,mixed> $payload
     * @return array<string,mixed> POST /video/storyboard (async -> {job_id})
     */
    public function generate(string $customPrompt, int $durationSeconds, ?array $selectedCandidate = null, ?string $productImageUrl = null, ?string $subjectImageUrl = null, bool $async = true, bool $deferReferenceFrames = true, array $payload = []): array
    {
        $body = $this->imageBody($productImageUrl, $subjectImageUrl, $payload);
        $body['custom_prompt'] = $customPrompt;
        $body['duration_seconds'] = $durationSeconds;
        $body['async_storyboard'] = $async;
        $body['defer_reference_frames'] = $deferReferenceFrames;
        if ($selectedCandidate !== null) {
            $body['selected_candidate'] = $selectedCandidate;
        }
        return $this->httpPost('/video/storyboard', ['json' => $body]);
    }

    /**
     * @param array<string,mixed> $storyboard
     * @param array<string,mixed> $payload
     * @return array<string,mixed> POST /video/storyboard/frames
     */
    public function frames(array $storyboard, ?string $productImageUrl = null, ?string $subjectImageUrl = null, bool $async = true, array $payload = []): array
    {
        $body = $this->imageBody($productImageUrl, $subjectImageUrl, $payload);
        $body['storyboard'] = $storyboard;
        $body['async_storyboard'] = $async;
        return $this->httpPost('/video/storyboard/frames', ['json' => $body]);
    }

    /**
     * @param array<string,mixed> $storyboard
     * @param array<string,mixed> $payload
     * @return array<string,mixed> POST /video/storyboard/revise
     */
    public function revise(string $revisionRequest, string $customPrompt, int $durationSeconds, array $storyboard, array $payload = []): array
    {
        $body = array_merge([
            'revision_request' => $revisionRequest,
            'custom_prompt' => $customPrompt,
            'duration_seconds' => $durationSeconds,
            'storyboard' => $storyboard,
        ], $payload);
        return $this->httpPost('/video/storyboard/revise', ['json' => $body]);
    }

    /** @return array<string,mixed> GET /video/storyboard/status/<job_id> */
    public function status(string $jobId): array
    {
        return $this->httpGet("/video/storyboard/status/{$jobId}");
    }

    /**
     * Poll a storyboard/frames job until done/failed.
     *
     * @param callable(array<string,mixed>):void|null $onProgress
     * @return array<string,mixed>
     */
    public function wait(string $jobId, float $interval = 3.0, float $timeout = 300.0, ?callable $onProgress = null): array
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

    /** @param array<string,mixed> $payload @return array<string,mixed> POST /subject/estimate-measurements */
    public function subjectMeasurements(array $payload = []): array
    {
        return $this->httpPost('/subject/estimate-measurements', ['json' => $payload]);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> POST /product/analyze */
    public function analyzeProduct(array $payload = []): array
    {
        return $this->httpPost('/product/analyze', ['json' => $payload]);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> POST /creativity/generate */
    public function creativity(array $payload = []): array
    {
        return $this->httpPost('/creativity/generate', ['json' => $payload]);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function imageBody(?string $productImageUrl, ?string $subjectImageUrl, array $payload): array
    {
        $body = $payload;
        if ($productImageUrl !== null) {
            $body['product_image_url'] = $this->resolveImage($productImageUrl, 'products');
        }
        if ($subjectImageUrl !== null) {
            $body['subject_image_url'] = $this->resolveImage($subjectImageUrl, 'subjects');
        }
        if (isset($body['image_url'])) {
            $body['image_url'] = $this->resolveImage($body['image_url'], 'references');
        }
        return $body;
    }
}
