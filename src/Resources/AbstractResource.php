<?php

declare(strict_types=1);

namespace HeroShots\Resources;

use HeroShots\Client;

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
     * Pass-through for null and existing http(s) URLs.
     */
    protected function resolveImage(?string $value, string $folder = 'references'): ?string
    {
        if (!$value) {
            return $value;
        }
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }
        if (is_file($value)) {
            return $this->client->uploads->image($value, $folder)['public_url'];
        }
        return $value;
    }

    /**
     * Build a Guzzle multipart array for a single file plus optional form fields.
     *
     * @param array<string,scalar> $fields
     * @return array<int,array<string,mixed>>
     */
    protected function multipart(string $path, array $fields = [], string $field = 'file'): array
    {
        $parts = [[
            'name' => $field,
            'contents' => fopen($path, 'rb'),
            'filename' => basename($path),
        ]];
        foreach ($fields as $name => $val) {
            $parts[] = ['name' => $name, 'contents' => (string) $val];
        }
        return $parts;
    }
}
