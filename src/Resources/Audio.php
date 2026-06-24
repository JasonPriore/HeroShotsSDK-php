<?php

declare(strict_types=1);

namespace HeroShots\Resources;

/** Voice cloning & TTS (portal JWT, /audio/*). */
class Audio extends AbstractResource
{
    /** @return array<string,mixed> GET /audio/voices */
    public function voices(): array
    {
        return $this->httpGet('/audio/voices');
    }

    /** @return array<string,mixed> POST /audio/clone (multipart file + name) -> {voice_id} */
    public function clone(string $path, string $name, ?string $text = null): array
    {
        $fields = ['name' => $name];
        if ($text !== null) {
            $fields['text'] = $text;
        }
        return $this->httpPost('/audio/clone', ['multipart' => $this->multipart($path, $fields)]);
    }

    /** @return array<string,mixed> DELETE /audio/delete */
    public function delete(string $voiceId, ?string $name = null): array
    {
        $body = ['voice_id' => $voiceId];
        if ($name !== null) {
            $body['name'] = $name;
        }
        return $this->httpDelete('/audio/delete', ['json' => $body]);
    }

    /** @return array<string,mixed> POST /audio/preview */
    public function preview(string $voiceId, string $text, ?string $languageCode = null): array
    {
        $body = ['voice_id' => $voiceId, 'text' => $text];
        if ($languageCode !== null) {
            $body['language_code'] = $languageCode;
        }
        return $this->httpPost('/audio/preview', ['json' => $body]);
    }
}
