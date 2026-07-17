<?php

declare(strict_types=1);

namespace HeroShots;

use GuzzleHttp\Client as Http;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use HeroShots\Errors\ApiException;
use HeroShots\Errors\TokenExpiredException;
use HeroShots\Resources\Avatars;
use HeroShots\Resources\Images;
use HeroShots\Resources\LongVideo;
use HeroShots\Resources\StillLife;
use HeroShots\Resources\Storyboard;
use HeroShots\Resources\Uploads;
use HeroShots\Resources\Videos;

/**
 * HeroShots API client (portal JWT or API key auth).
 *
 * Handles the two backend envelope styles: /api/v1/* responses are wrapped in
 * {"success","data","meta"} and unwrapped to data; everything else is bare JSON.
 */
class Client
{
    public const DEFAULT_BASE_URL = 'https://app.heroshots.ai';

    public string $baseUrl;
    public ?string $token;
    public string|int|null $managedUserId;
    private ?string $email;
    private ?string $password;
    private bool $autoRelogin;
    private ClientInterface $http;

    public Avatars $avatars;
    public Images $images;
    public LongVideo $longvideo;
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
        ?ClientInterface $http = null,
        string|int|null $managedUserId = null
    ) {
        $baseUrl = $baseUrl ?: (getenv('HEROSHOTS_BASE_URL') ?: self::DEFAULT_BASE_URL);
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
        $this->managedUserId = $managedUserId;
        $this->email = $email;
        $this->password = $password;
        $this->autoRelogin = $autoRelogin;
        $this->http = $http ?? new Http(['timeout' => $timeout, 'http_errors' => false]);

        $this->avatars = new Avatars($this);
        $this->images = new Images($this);
        $this->longvideo = new LongVideo($this);
        $this->stilllife = new StillLife($this);
        $this->storyboard = new Storyboard($this);
        $this->uploads = new Uploads($this);
        $this->videos = new Videos($this);
    }

    /** Base URL defaults to https://app.heroshots.ai. */
    public static function fromLogin(string $email, string $password, ?string $baseUrl = null): self
    {
        $client = new self($baseUrl, null, $email, $password);
        $client->login($email, $password);
        return $client;
    }

    /** @return array<string,mixed> POST /api/auth/login -> stores and returns the JWT + profile. */
    public function login(string $email, string $password): array
    {
        $data = $this->request('POST', '/api/auth/login', ['json' => ['email' => $email, 'password' => $password], 'auth' => false]);
        $this->token = $data['token'] ?? null;
        $this->email = $email;
        $this->password = $password;
        return $data;
    }

    /** @return array<string,mixed> GET /api/auth/me -> current authenticated profile. */
    public function me(): array
    {
        return $this->request('GET', '/api/auth/me');
    }

    /** @return array<string,mixed> GET /api/auth/stats -> full raw account stats. */
    public function stats(): array
    {
        $stats = $this->request('GET', '/api/auth/stats');
        return is_array($stats) ? $stats : [];
    }

    /**
     * Return a compact account balance view from /api/auth/stats.
     *
     * Use stats() when you need the full raw stats payload.
     *
     * @return array<string,mixed>
     */
    public function balance(): array
    {
        $stats = $this->stats();
        $plan = isset($stats['plan']) && is_array($stats['plan']) ? $stats['plan'] : [];

        $balance = [];
        foreach ([
            'hs_credits',
            'total_credits_used',
            'total_cost_charged',
            'current_month_cost',
            'current_month_generations',
            'total_generations',
            'video_package_credits_remaining',
            'free_preview_videos_remaining',
            'use_wallet',
        ] as $key) {
            if (array_key_exists($key, $stats)) {
                $balance[$key] = $stats[$key];
            }
        }

        $compactPlan = [];
        foreach ([
            'wallet_balance',
            'template_id',
            'billing_type',
            'output_resolution',
            'has_watermark',
            'offered_plan_id',
            'monthly_price',
        ] as $key) {
            if (array_key_exists($key, $plan)) {
                $compactPlan[$key] = $plan[$key];
            }
        }

        if ($compactPlan !== []) {
            $balance['plan'] = $compactPlan;
        }
        if (array_key_exists('wallet_balance', $compactPlan)) {
            $balance['wallet_balance'] = $compactPlan['wallet_balance'];
        }

        return $balance;
    }

    public function withManagedUser(string|int|null $managedUserId): self
    {
        $this->managedUserId = $managedUserId;
        return $this;
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
        if ($auth && $this->managedUserId !== null) {
            $headers['X-Managed-User-Id'] = (string) $this->managedUserId;
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
