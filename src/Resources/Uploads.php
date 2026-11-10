<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/**
 * File uploads -> hosted public URLs (portal JWT only).
 *
 * Every $file is a path, a string of bytes or an open stream resource.
 */
class Uploads extends AbstractResource
{
    public const FOLDERS = [
        'subjects', 'products', 'avatars', 'avatar-consents',
        'references', 'background', 'body_posture', 'reference-videos',
    ];

    /**
     * POST /upload/image (multipart "file") -> {public_url, filename, folder}.
     *
     * $folder is one of FOLDERS (or null).
     *
     * @param string|resource $file
     * @return array<string,mixed>
     */
    public function image(mixed $file, ?string $folder = null): array
    {
        $fields = $folder ? ['folder' => $folder] : [];
        return $this->httpPost('/upload/image', ['multipart' => $this->multipart($file, $fields)]);
    }

    /**
     * Upload a signed release or ID document (PDF, DOCX, JPEG, PNG, WebP).
     *
     * Stored privately and bound to your account; pass the returned URL as
     * release_doc_url / id_doc_url.
     *
     * @param string|resource $file
     * @return array<string,mixed>
     */
    public function consentDocument(mixed $file): array
    {
        return $this->image($file, 'avatar-consents');
    }

    /**
     * POST /upload/reference-audio -> hosted MP3/WAV for video lip-sync.
     *
     * @param string|resource $file
     * @return array<string,mixed>
     */
    public function referenceAudio(mixed $file): array
    {
        return $this->httpPost('/upload/reference-audio', ['multipart' => $this->multipart($file)]);
    }
}
