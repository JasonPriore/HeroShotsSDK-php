<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/** File uploads -> hosted public URLs (portal JWT). */
class Uploads extends AbstractResource
{
    public const FOLDERS = [
        'subjects', 'products', 'avatars', 'avatar-consents',
        'references', 'background', 'body_posture', 'reference-videos',
    ];

    /** @return array<string,mixed> POST /upload/image -> {public_url, filename, folder} */
    public function image(string $path, ?string $folder = null): array
    {
        $fields = $folder ? ['folder' => $folder] : [];
        return $this->httpPost('/upload/image', ['multipart' => $this->multipart($path, $fields)]);
    }

    /** @return array<string,mixed> POST /upload/reference-audio */
    public function referenceAudio(string $path): array
    {
        return $this->httpPost('/upload/reference-audio', ['multipart' => $this->multipart($path)]);
    }
}
