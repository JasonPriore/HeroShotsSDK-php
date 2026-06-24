<?php

declare(strict_types=1);

namespace HeroShots;

use GuzzleHttp\Client as Http;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use HeroShots\Errors\ApiException;
use HeroShots\Errors\TokenExpiredException;
use HeroShots\Resources\Account;
use HeroShots\Resources\Audio;
use HeroShots\Resources\Avatars;
use HeroShots\Resources\Billing;
use HeroShots\Resources\Bulk;
use HeroShots\Resources\Connectors;
use HeroShots\Resources\Dreamcast;
use HeroShots\Resources\Generations;
use HeroShots\Resources\Images;
use HeroShots\Resources\Library;
use HeroShots\Resources\LongVideo;
use HeroShots\Resources\Model;
use HeroShots\Resources\Pricing;
use HeroShots\Resources\StillLife;
use HeroShots\Resources\Storyboard;
use HeroShots\Resources\Uploads;
use HeroShots\Resources\Videos;

/**
 * HeroShots API client (portal JWT auth).
 *
 * Handles the two backend envelope styles: /api/v1/* responses are wrapped in
 * {"success","data","meta"} and unwrapped to data; everything else is bare JSON.
 */
class Client
{
    public string $baseUrl;
    public ?string $token;
    private ?string $email;
    private ?string $password;
    private bool $autoRelogin;
    private ClientInterface $http;

    public Account $account;
    public Audio $audio;
    public Avatars $avatars;
    public Billing $billing;
    public Bulk $bulk;
    public Connectors $connectors;
    public Dreamcast $dreamcast;
    public Generations $generations;
    public Images $images;
    public Library $library;
    public LongVideo $longvideo;
    public Model $model;
    public Pricing $pricing;
    public StillLife $stilllife;
    public Storyboard $storyboard;
    public Uploads $uploads;
    public Videos $videos;

    public function __construct(
        ?string $baseUrl = null,
        ?string $token = null,
        ?string $email = null,
        ?string $password = null,
        bool $autoRelogin = true,
        float $timeout = 120.0,
        ?ClientInterface $http = null
    ) {
        $baseUrl = $baseUrl ?? getenv('HEROSHOTS_BASE_URL') ?: null;
        if (!$baseUrl) {
            throw new \InvalidArgumentException('baseUrl is required (or set HEROSHOTS_BASE_URL)');
        }
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
        $this->email = $email;
        $this->password = $password;
        $this->autoRelogin = $autoRelogin;
        $this->http = $http ?? new Http(['timeout' => $timeout, 'http_errors' => false]);

        $this->account = new Account($this);
        $this->audio = new Audio($this);
        $this->avatars = new Avatars($this);
        $this->billing = new Billing($this);
        $this->bulk = new Bulk($this);
        $this->connectors = new Connectors($this);
        $this->dreamcast = new Dreamcast($this);
        $this->generations = new Generations($this);
        $this->images = new Images($this);
        $this->library = new Library($this);
        $this->longvideo = new LongVideo($this);
        $this->model = new Model($this);
        $this->pricing = new Pricing($this);
        $this->stilllife = new StillLife($this);
        $this->storyboard = new Storyboard($this);
        $this->uploads = new Uploads($this);
        $this->videos = new Videos($this);
    }

    public static function fromLogin(string $baseUrl, string $email, string $password): self
    {
        $client = new self($baseUrl, null, $email, $password);
        $client->login($email, $password);
        return $client;
    }

    /** @return array<string,mixed> */
    public function login(string $email, string $password): array
    {
        $data = $this->request('POST', '/api/auth/login', ['json' => ['email' => $email, 'password' => $password], 'auth' => false]);
        $this->token = $data['token'] ?? null;
        $this->email = $email;
        $this->password = $password;
        return $data;
    }

    /**
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public function register(string $email, string $password, string $fullName, array $extra = []): array
    {
        $body = array_merge(['email' => $email, 'password' => $password, 'full_name' => $fullName], $extra);
        $data = $this->request('POST', '/api/auth/register', ['json' => $body, 'auth' => false]);
        $this->token = $data['token'] ?? null;
        return $data;
    }

    /** @return array<string,mixed> */
    public function me(): array
    {
        return $this->request('GET', '/api/auth/me');
    }

    /**
     * Perform an HTTP request, returning decoded JSON or throwing an ApiException.
     *
     * Options: json (array), query (array), multipart (array), auth (bool),
     * unwrap (bool|null - inferred from path when null).
     *
     * @param array<string,mixed> $options
     * @return mixed
     */
    public function request(string $method, string $path, array $options = [], bool $retried = false): mixed
    {
        $auth = $options['auth'] ?? true;
        $unwrap = $options['unwrap'] ?? null;

        $guzzle = [];
        if (isset($options['json'])) {
            $guzzle['json'] = $options['json'];
        }
        if (isset($options['query'])) {
            $guzzle['query'] = $options['query'];
        }
        if (isset($options['multipart'])) {
            $guzzle['multipart'] = $options['multipart'];
        }
        $headers = [];
        if ($auth && $this->token) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }
        $guzzle['headers'] = $headers;

        try {
            $resp = $this->http->request($method, $this->baseUrl . $path, $guzzle);
        } catch (RequestException $e) {
            throw new ApiException($e->getMessage(), 'network_error', null);
        }

        $status = $resp->getStatusCode();
        $raw = (string) $resp->getBody();
        $body = $raw === '' ? null : json_decode($raw, true);

        if ($status < 200 || $status >= 300) {
            $err = ApiException::fromResponse($status, is_array($body) ? $body : null);
            if (
                $err instanceof TokenExpiredException
                && $this->autoRelogin
                && !$retried
                && $this->email
                && $this->password
            ) {
                $this->login($this->email, $this->password);
                return $this->request($method, $path, $options, true);
            }
            throw $err;
        }

        if ($unwrap === null) {
            $unwrap = str_starts_with($path, '/api/v1/');
        }
        if ($unwrap && is_array($body) && array_key_exists('data', $body)) {
            $data = $body['data'];
            if (is_array($data) && isset($body['meta']) && !isset($data['_meta'])) {
                $data['_meta'] = $body['meta'];
            }
            return $data;
        }
        return $body;
    }
}
