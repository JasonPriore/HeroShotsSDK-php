<?php

declare(strict_types=1);

namespace HeroShots\Resources;

use HeroShots\Client;
use HeroShots\Errors\HeroShotsException;

abstract class AbstractResource
{
    public function __construct(protected Client $client)
    {
    }

    // Named httpX (not get/post/delete) so resource methods like delete($id)
    // can shadow the REST verb without recursing into the transport.

    /** @param array<string,mixed> $options @return mixed */
    protected function httpGet(string $path, array $options = []): mixed
    {
        return $this->client->request('GET', $path, $options);
    }

    /** @param array<string,mixed> $options @return mixed */
    protected function httpPost(string $path, array $options = []): mixed
    {
        return $this->client->request('POST', $path, $options);
    }

    /** @param array<string,mixed> $options @return mixed */
    protected function httpPatch(string $path, array $options = []): mixed
    {
        return $this->client->request('PATCH', $path, $options);
    }

    /** @param array<string,mixed> $options @return mixed */
    protected function httpPut(string $path, array $options = []): mixed
    {
        return $this->client->request('PUT', $path, $options);
    }

    /** @param array<string,mixed> $options @return mixed */
    protected function httpDelete(string $path, array $options = []): mixed
    {
        return $this->client->request('DELETE', $path, $options);
    }

    /**
     * If $value is a local file path, upload it and return the hosted URL.
     *
     * Pass-through for null/non-strings, http(s):// and data: values, and
     * anything that is not an existing file. Uploading needs a portal JWT: with an
     * API key, pass public https:// URLs.
     */
    protected function resolveImage(mixed $value, string $folder = 'references'): mixed
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, 'data:')) {
            return $value;
        }
        if (is_file($value)) {
            if ($this->client->usesApiKey()) {
                throw new HeroShotsException(
                    "Cannot upload local file '{$value}' with an API key: /upload/image "
                    . 'accepts only a portal login. Pass a public https:// URL instead, '
                    . 'or use Client::fromLogin().'
                );
            }
            return $this->client->uploads->image($value, $folder)['public_url'];
        }
        return $value;
    }

    /**
     * Drop null values so optional arguments are not sent.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    protected static function compact(array $payload): array
    {
        return array_filter($payload, static fn ($v) => $v !== null);
    }

    /**
     * Build a Guzzle multipart array for a single file plus optional form fields.
     *
     * $file is a path, a string of bytes or an open stream resource.
     *
     * @param array<string,scalar|null> $fields
     * @return array<int,array<string,mixed>>
     */
    protected function multipart(mixed $file, array $fields = [], string $field = 'file'): array
    {
        $parts = [$this->filePart($file, $field)];
        foreach ($fields as $name => $val) {
            if ($val === null) {
                continue;
            }
            $parts[] = ['name' => $name, 'contents' => (string) $val];
        }
        return $parts;
    }

    /**
     * One multipart file part from a path, a string of bytes or a stream resource.
     *
     * A string that is an existing file path is read from disk; any other string
     * is sent as raw bytes (named $defaultName).
     *
     * @return array<string,mixed>
     */
    protected function filePart(mixed $file, string $field = 'file', string $defaultName = 'file'): array
    {
        if (is_resource($file)) {
            $uri = stream_get_meta_data($file)['uri'] ?? null;
            $name = is_string($uri) && $uri !== '' && !str_starts_with($uri, 'php://') ? basename($uri) : $defaultName;
            return ['name' => $field, 'contents' => $file, 'filename' => $name];
        }
        if (!is_string($file)) {
            throw new HeroShotsException('File must be a path, a string of bytes or a stream resource.');
        }
        if (self::looksLikePath($file)) {
            if (!is_file($file)) {
                throw new HeroShotsException("File not found: {$file}");
            }
            return ['name' => $field, 'contents' => fopen($file, 'rb'), 'filename' => basename($file)];
        }
        return ['name' => $field, 'contents' => $file, 'filename' => $defaultName];
    }

    /**
     * Existing file, or a short NUL-free string ending in a known file extension
     * (a missing path must fail loudly instead of being uploaded as bytes).
     */
    private static function looksLikePath(string $value): bool
    {
        if ($value === '' || strlen($value) > PHP_MAXPATHLEN || str_contains($value, "\0")) {
            return false;
        }
        if (is_file($value)) {
            return true;
        }
        return preg_match('/^[^\r\n]*\.(jpe?g|png|webp|gif|heic|avif|bmp|tiff?|pdf|docx?|mp3|wav|m4a|mp4|mov|webm|zip)$/i', $value) === 1;
    }
}
