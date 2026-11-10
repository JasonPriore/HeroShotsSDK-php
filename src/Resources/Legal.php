<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/**
 * BYO compliance documents (portal JWT only, /api/legal/byo).
 *
 * Real-person ("bring your own") avatars need the company manleva (once per
 * brand) and a signed liberatoria + identity document for each person.
 */
class Legal extends AbstractResource
{
    /** @return array<string,mixed> GET /api/legal/byo/status -> manleva status and can_create_byo */
    public function byoStatus(): array
    {
        return $this->httpGet('/api/legal/byo/status');
    }

    /** GET /api/legal/byo/template -> company manleva template (DOCX bytes, or the saved path). */
    public function manlevaTemplate(?string $dest = null): string
    {
        return $this->client->download('/api/legal/byo/template', $dest);
    }

    /** GET /api/legal/byo/liberatoria-template -> per-person liberatoria template (DOCX bytes, or the saved path). */
    public function liberatoriaTemplate(?string $dest = null): string
    {
        return $this->client->download('/api/legal/byo/liberatoria-template', $dest);
    }

    /**
     * POST /api/legal/byo/<tenant_id>/manleva (multipart) -> signed company manleva.
     *
     * @param string|resource $file path, string of bytes or stream resource
     * @return array<string,mixed>
     */
    public function uploadManleva(
        int|string $tenantId,
        mixed $file,
        ?string $signedByName = null,
        ?string $signedByRole = null,
        ?string $documentVersion = null,
        ?string $templateDownloadReference = null
    ): array {
        $fields = self::compact([
            'signed_by_name' => $signedByName,
            'signed_by_role' => $signedByRole,
            'document_version' => $documentVersion,
            'template_download_reference' => $templateDownloadReference,
        ]);
        return $this->httpPost("/api/legal/byo/{$tenantId}/manleva", ['multipart' => $this->multipart($file, $fields)]);
    }

    /**
     * POST /api/legal/byo/<tenant_id>/avatars -> attach a signed liberatoria to an avatar.
     *
     * Upload the signed release and the ID with $client->uploads->consentDocument()
     * first and pass the returned URLs.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function attachLiberatoria(
        int|string $tenantId,
        int|string $avatarId,
        string $releaseDocUrl,
        string $idDocUrl,
        string $modelEmail,
        string $modelDob,
        array $payload = []
    ): array {
        $body = array_merge([
            'avatar_id' => (int) $avatarId,
            'release_doc_url' => $releaseDocUrl,
            'id_doc_url' => $idDocUrl,
            'model_email' => $modelEmail,
            'model_dob' => $modelDob,
        ], $payload);
        return $this->httpPost("/api/legal/byo/{$tenantId}/avatars", ['json' => $body]);
    }
}
