<?php

declare(strict_types=1);

namespace HeroShots\Resources;

use HeroShots\Polling;

/** Image generation (API key or portal JWT on /api/v1; portal-only candidates flow). */
class Images extends AbstractResource
{
    /** Extra image URL fields resolved in the body, with their upload folder. */
    private const IMAGE_URL_FIELDS = [
        'background_image_url' => 'background',
        'body_posture_image_url' => 'body_posture',
        'hairstyle_reference_image_url' => 'references',
        'text_detail_image_url' => 'products',
        'worn_scale_reference_url' => 'references',
    ];

    /**
     * POST /api/v1/image/generate (async) -> {generation_id, status, ...}.
     *
     * Provide $subjectImageUrl or avatar_id (avatar_image_set: original | generated)
     * unless still_life is true. Other fields: custom_prompt, size (720p | 1080p |
     * 2K | 4K), aspect_ratio, view_type, background_image_url,
     * additional_product_image_urls, quality_mode, text_fidelity_mode. To edit an
     * earlier image pass edit_generation_id or previous_output_image_url.
     * Local file paths are auto-uploaded (portal JWT only).
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function generate(string $productImageUrl, ?string $subjectImageUrl = null, array $payload = []): array
    {
        $body = $this->imageBody($productImageUrl, $subjectImageUrl, $payload);
        return $this->httpPost('/api/v1/image/generate', ['json' => $body]);
    }

    /**
     * POST /api/v1/background/generate -> AI background image (size: 2K | 4K).
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function generateBackground(string $prompt, ?string $aspectRatio = null, ?string $size = null, ?bool $asyncGeneration = null, array $payload = []): array
    {
        $body = self::compact(array_merge([
            'prompt' => $prompt,
            'aspect_ratio' => $aspectRatio,
            'size' => $size,
            'async_generation' => $asyncGeneration,
        ], $payload));
        return $this->httpPost('/api/v1/background/generate', ['json' => $body]);
    }

    /**
     * POST /api/auth/image/generate/candidates -> persistent candidate batch (portal JWT).
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function candidates(string $productImageUrl, ?string $subjectImageUrl = null, array $payload = []): array
    {
        $body = $this->imageBody($productImageUrl, $subjectImageUrl, $payload);
        return $this->httpPost('/api/auth/image/generate/candidates', ['json' => $body]);
    }

    /**
     * POST /api/auth/image/generate/candidates/<token>/select (portal JWT).
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function selectCandidate(string $token, array $payload = []): array
    {
        return $this->httpPost("/api/auth/image/generate/candidates/{$token}/select", ['json' => $payload]);
    }

    /** @return array<string,mixed> GET /api/v1/image/status/<generation_id> */
    public function status(string $generationId): array
    {
        return $this->httpGet("/api/v1/image/status/{$generationId}");
    }

    /**
     * Poll image status until succeeded/failed.
     *
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

    /**
     * GET /api/v1/image/<log_id>/download -> image bytes, or the saved path when
     * $dest is given. Same watermark and resolution as at generation time.
     */
    public function download(int|string $logId, ?string $dest = null): string
    {
        return $this->client->download("/api/v1/image/{$logId}/download", $dest);
    }

    // -- usage rights -----------------------------------------------------------

    /**
     * Quote a usage-rights upgrade for an image -> {delta_eur, new_total_eur, ...}.
     *
     * Pass a $usageRights list (POST body) or a single $usageRightsType
     * (organic | paid_digital | broadcast) + $usageRightsDuration
     * (4_weeks | 6_months | 2_years | perpetual) as a GET query.
     *
     * @param array<int,array<string,mixed>>|null $usageRights
     * @return array<string,mixed>
     */
    public function usageRightsQuote(int|string $logId, ?array $usageRights = null, ?string $usageRightsType = null, ?string $usageRightsDuration = null): array
    {
        $path = "/api/v1/image/{$logId}/usage-rights/quote";
        if ($usageRights !== null) {
            return $this->httpPost($path, ['json' => ['usage_rights' => $usageRights]]);
        }
        $query = self::compact([
            'usage_rights_type' => $usageRightsType,
            'usage_rights_duration' => $usageRightsDuration,
        ]);
        return $this->httpGet($path, $query ? ['query' => $query] : []);
    }

    /**
     * POST /api/v1/image/<log_id>/usage-rights/upgrade -> charges the difference.
     *
     * @param array<int,array<string,mixed>> $usageRights
     * @return array<string,mixed>
     */
    public function usageRightsUpgrade(int|string $logId, array $usageRights): array
    {
        return $this->httpPost("/api/v1/image/{$logId}/usage-rights/upgrade", ['json' => ['usage_rights' => $usageRights]]);
    }

    /**
     * POST /api/v1/image/analyze-edit-region -> {edit_prompt} for a cropped area.
     *
     * $cropImage is a data URI or URL of the area to change; $feedback is what
     * should change there.
     *
     * @return array<string,mixed>
     */
    public function analyzeEditRegion(string $cropImage, string $feedback): array
    {
        return $this->httpPost('/api/v1/image/analyze-edit-region', ['json' => ['crop_image' => $cropImage, 'feedback' => $feedback]]);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function imageBody(string $productImageUrl, ?string $subjectImageUrl, array $payload): array
    {
        $body = $payload;
        $body['product_image_url'] = $this->resolveImage($productImageUrl, 'products');
        if ($subjectImageUrl !== null) {
            $body['subject_image_url'] = $this->resolveImage($subjectImageUrl, 'subjects');
        }
        foreach (self::IMAGE_URL_FIELDS as $key => $folder) {
            if (array_key_exists($key, $body)) {
                $body[$key] = $this->resolveImage($body[$key], $folder);
            }
        }
        if (isset($body['additional_product_image_urls']) && is_array($body['additional_product_image_urls'])) {
            $body['additional_product_image_urls'] = array_map(
                fn ($u) => $this->resolveImage($u, 'products'),
                $body['additional_product_image_urls']
            );
        }
        return $body;
    }
}
