<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/** Avatars (API key or portal JWT, /api/v1/avatar). */
class Avatars extends AbstractResource
{
    /** Photo views accepted by the avatar endpoints. */
    public const VIEW_TYPES = ['closeup', 'fullbody', 'profile', 'back', 'profile_left', 'profile_right', 'mez'];

    // -- read -------------------------------------------------------------------

    /**
     * GET /api/v1/avatar/list -> {avatars, total, page, pages, counts, ...}.
     *
     * Filters: gender, nationality, min_age, max_age, avatar_type, category_id,
     * q, tab, page, per_page.
     *
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public function list(array $query = []): array
    {
        $query = self::compact($query);
        return $this->httpGet('/api/v1/avatar/list', $query ? ['query' => $query] : []);
    }

    /** @return array<string,mixed> GET /api/v1/avatar/<id> */
    public function get(int|string $avatarId): array
    {
        return $this->httpGet("/api/v1/avatar/{$avatarId}");
    }

    /**
     * GET /api/v1/avatar/<id>/consent-document -> signed consent (DOCX bytes, or
     * the saved path when $dest is given).
     */
    public function consentDocument(int|string $avatarId, ?string $dest = null, ?string $kind = null): string
    {
        return $this->client->download("/api/v1/avatar/{$avatarId}/consent-document", $dest, $kind ? ['kind' => $kind] : []);
    }

    // -- create -----------------------------------------------------------------

    /**
     * POST /api/v1/avatar/generate -> new avatar (AI or from image_url).
     *
     * AI: generate_with_ai = true + prompt (and gender, age, nationality, body
     * measurements). Real person: image_url plus release_doc_url, id_doc_url,
     * model_email, model_dob.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function generate(array $payload = [], ?string $name = null): array
    {
        $body = $payload;
        if ($name !== null) {
            $body['name'] = $name;
        }
        if (array_key_exists('image_url', $body)) {
            $body['image_url'] = $this->resolveImage($body['image_url'], 'avatars');
        }
        return $this->httpPost('/api/v1/avatar/generate', ['json' => $body]);
    }

    /**
     * POST /api/v1/avatar/create-from-steps -> avatar from one photo per view.
     *
     * @param array<string,string> $imagesByView view type => image URL (see VIEW_TYPES)
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function createFromSteps(string $name, array $imagesByView, array $payload = []): array
    {
        $views = [];
        foreach ($imagesByView as $view => $url) {
            $views[$view] = $this->resolveImage($url, 'avatars');
        }
        return $this->httpPost('/api/v1/avatar/create-from-steps', [
            'json' => array_merge(['name' => $name, 'images_by_view' => $views], $payload),
        ]);
    }

    /**
     * POST /api/v1/avatar/improve-prompt -> {improved_prompt}.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function improvePrompt(string $prompt, array $payload = []): array
    {
        return $this->httpPost('/api/v1/avatar/improve-prompt', ['json' => array_merge(['prompt' => $prompt], $payload)]);
    }

    /** @return array<string,mixed> POST /api/v1/avatar/analyze-attributes -> gender/age/nationality inferred from text */
    public function analyzeAttributes(string $prompt): array
    {
        return $this->httpPost('/api/v1/avatar/analyze-attributes', ['json' => ['prompt' => $prompt]]);
    }

    // -- edit -------------------------------------------------------------------

    /**
     * PATCH /api/v1/avatar/<id> -> name, age, gender, nationality, measurements...
     *
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    public function update(int|string $avatarId, array $fields = []): array
    {
        return $this->httpPatch("/api/v1/avatar/{$avatarId}", ['json' => $fields]);
    }

    /** @return mixed DELETE /api/v1/avatar/<id> */
    public function delete(int|string $avatarId): mixed
    {
        return $this->httpDelete("/api/v1/avatar/{$avatarId}");
    }

    /** @return array<string,mixed> POST /api/v1/avatar/<id>/upload-view -> add/replace one photo view */
    public function uploadView(int|string $avatarId, string $viewType, string $imageUrl): array
    {
        return $this->httpPost("/api/v1/avatar/{$avatarId}/upload-view", [
            'json' => ['view_type' => $viewType, 'image_url' => $this->resolveImage($imageUrl, 'avatars')],
        ]);
    }

    /** @return array<string,mixed> POST /api/v1/avatar/<id>/regenerate-view (async, 202) */
    public function regenerateView(int|string $avatarId, string $viewType, ?string $prompt = null, ?string $outputStyle = null): array
    {
        $body = self::compact(['view_type' => $viewType, 'prompt' => $prompt, 'output_style' => $outputStyle]);
        return $this->httpPost("/api/v1/avatar/{$avatarId}/regenerate-view", ['json' => $body]);
    }

    /** @return array<string,mixed> POST /api/v1/avatar/<id>/regenerate-variants (async, 202) */
    public function regenerateVariants(int|string $avatarId, ?string $outputStyle = null): array
    {
        $body = self::compact(['output_style' => $outputStyle]);
        return $this->httpPost("/api/v1/avatar/{$avatarId}/regenerate-variants", ['json' => $body]);
    }

    /**
     * POST /api/v1/avatar/<id>/generated-version -> AI version of a photo avatar (async).
     *
     * @param string[]|null $extraReferenceUrls
     * @return array<string,mixed>
     */
    public function generatedVersion(int|string $avatarId, ?string $prompt = null, ?array $extraReferenceUrls = null, ?string $outputStyle = null): array
    {
        $body = self::compact([
            'prompt' => $prompt,
            'extra_reference_urls' => $extraReferenceUrls,
            'output_style' => $outputStyle,
        ]);
        return $this->httpPost("/api/v1/avatar/{$avatarId}/generated-version", ['json' => $body]);
    }

    /** @return array<string,mixed> POST /api/v1/avatar/<id>/image-set -> use "original" or "generated" photos */
    public function setImageSet(int|string $avatarId, string $mode): array
    {
        return $this->httpPost("/api/v1/avatar/{$avatarId}/image-set", ['json' => ['mode' => $mode]]);
    }

    /** @return array<string,mixed> POST /api/v1/avatar/<id>/generate-avatar-sheet ($force = true regenerates) */
    public function generateSheet(int|string $avatarId, ?bool $force = null, ?string $prompt = null, ?string $outputStyle = null): array
    {
        $body = self::compact(['force' => $force, 'prompt' => $prompt, 'output_style' => $outputStyle]);
        return $this->httpPost("/api/v1/avatar/{$avatarId}/generate-avatar-sheet", ['json' => $body]);
    }

    /** @return array<string,mixed> POST /api/v1/avatar/<id>/generate-voice -> avatar voice (async, 202) */
    public function generateVoice(int|string $avatarId, ?string $generationNote = null, ?bool $force = null): array
    {
        $body = self::compact(['generation_note' => $generationNote, 'force' => $force]);
        return $this->httpPost("/api/v1/avatar/{$avatarId}/generate-voice", ['json' => $body]);
    }

    // -- outfit -----------------------------------------------------------------

    /** @return array<string,mixed> POST /api/v1/avatar/outfit/resolve-link -> product photos behind a shop link */
    public function resolveOutfitLink(string $url): array
    {
        return $this->httpPost('/api/v1/avatar/outfit/resolve-link', ['json' => ['url' => $url]]);
    }

    /**
     * POST /api/v1/avatar/<id>/outfit -> dress the avatar (top, bottom, shoes...).
     *
     * @param array<int,array<string,mixed>> $items
     * @return array<string,mixed>
     */
    public function changeOutfit(int|string $avatarId, array $items, ?bool $regenerateViews = null): array
    {
        $body = self::compact(['items' => $items, 'regenerate_views' => $regenerateViews]);
        return $this->httpPost("/api/v1/avatar/{$avatarId}/outfit", ['json' => $body]);
    }

    /** @return array<string,mixed> POST /api/v1/avatar/<id>/outfit/restore -> undo the last outfit change */
    public function restoreOutfit(int|string $avatarId): array
    {
        return $this->httpPost("/api/v1/avatar/{$avatarId}/outfit/restore", ['json' => []]);
    }
}
