<?php

declare(strict_types=1);

namespace HeroShots;

use GuzzleHttp\Client as Http;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
use HeroShots\Errors\ApiException;
use HeroShots\Errors\HeroShotsException;
use HeroShots\Errors\RateLimitException;
use HeroShots\Errors\TokenExpiredException;
use HeroShots\Resources\Account;
use HeroShots\Resources\Agency;
use HeroShots\Resources\Assets;
use HeroShots\Resources\Avatars;
use HeroShots\Resources\Backgrounds;
use HeroShots\Resources\Brand;
use HeroShots\Resources\Bulk;
use HeroShots\Resources\Categories;
use HeroShots\Resources\Collections;
use HeroShots\Resources\Generations;
use HeroShots\Resources\Images;
use HeroShots\Resources\Legal;
use HeroShots\Resources\LongVideo;
use HeroShots\Resources\Pricing;
use HeroShots\Resources\StillLife;
use HeroShots\Resources\Storyboard;
use HeroShots\Resources\Uploads;
use HeroShots\Resources\Videos;
use Psr\Http\Message\ResponseInterface;

/**
 * HeroShots API client.
 *
 * Authenticates with a B2B API key (lz_live_...) or a portal JWT, both sent as
 * "Authorization: Bearer <token>". Portal JWTs come from POST /api/auth/login
 * (7-day TTL, no refresh endpoint - the client logs in again once on
 * token_expired when it knows the credentials).
 *
 * Which credential works where:
 *  - /api/v1/* (images, videos, long video, still life, avatars, library, bulk,
 *    generations, pricing, agency, brand) accepts both API keys and portal JWTs.
 *  - Portal routes (storyboard, uploads, image candidates, still-life background
 *    edits, legal) accept only a portal JWT.
 *  - API keys are refused on the Free plan (plan_not_allowed), except for agencies.
 *
 * Response envelopes: /api/v1/* responses are wrapped in {"success","data","meta"}
 * and unwrapped to data (meta is kept under data["_meta"]); everything else is
 * bare JSON, returned as-is.
 */
class Client
{
    public const VERSION = '0.3.1';
    public const DEFAULT_BASE_URL = 'https://app.heroshots.ai';
    public const DEFAULT_TIMEOUT = 120.0;
    public const DEFAULT_MAX_RETRIES = 2;
    public const API_KEY_PREFIX = 'lz_';

    public string $baseUrl;
    public ?string $token;
    public string|int|null $managedUserId;
    public int $maxRetries;
    /**
     * Called with the delay in seconds before a 429 retry. Override in tests.
     *
     * @var callable(float):void
     */
    public mixed $sleeper;
    private ?string $email;
    private ?string $password;
    private bool $autoRelogin;
    private ClientInterface $http;

    // Account / auth helpers.
    public Account $account;
    // Generation.
    public Images $images;
    public Videos $videos;
    public LongVideo $longvideo;
    public StillLife $stilllife;
    public Storyboard $storyboard;
    public Avatars $avatars;
    public Bulk $bulk;
    public Generations $generations;
    public Pricing $pricing;
    // Library.
    public Assets $assets;
    public Collections $collections;
    public Backgrounds $backgrounds;
    public Categories $categories;
    // Files, compliance, relationships.
    public Uploads $uploads;
    public Legal $legal;
    public Agency $agency;
    public Brand $brand;

    /**
     * $token and $apiKey are aliases (the API key is sent as a bearer token too);
     * when both are null the HEROSHOTS_API_KEY environment variable is used.
     */
    public function __construct(
        ?string $baseUrl = null,
        ?string $token = null,
        ?string $email = null,
        ?string $password = null,
        bool $autoRelogin = true,
        float $timeout = self::DEFAULT_TIMEOUT,
        ?ClientInterface $http = null,
        string|int|null $managedUserId = null,
        int $maxRetries = self::DEFAULT_MAX_RETRIES,
        ?string $apiKey = null
    ) {
        $baseUrl = $baseUrl ?: (getenv('HEROSHOTS_BASE_URL') ?: self::DEFAULT_BASE_URL);
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token ?: ($apiKey ?: (getenv('HEROSHOTS_API_KEY') ?: null));
        $this->managedUserId = $managedUserId;
        $this->email = $email;
        $this->password = $password;
        $this->autoRelogin = $autoRelogin;
        $this->maxRetries = max(0, $maxRetries);
        $this->sleeper = static function (float $seconds): void {
            if ($seconds > 0) {
                usleep((int) round($seconds * 1_000_000));
            }
        };
        $this->http = $http ?? new Http(['timeout' => $timeout, 'http_errors' => false]);

        $this->account = new Account($this);
        $this->images = new Images($this);
        $this->videos = new Videos($this);
        $this->longvideo = new LongVideo($this);
        $this->stilllife = new StillLife($this);
        $this->storyboard = new Storyboard($this);
        $this->avatars = new Avatars($this);
        $this->bulk = new Bulk($this);
        $this->generations = new Generations($this);
        $this->pricing = new Pricing($this);
        $this->assets = new Assets($this);
        $this->collections = new Collections($this);
        $this->backgrounds = new Backgrounds($this);
        $this->categories = new Categories($this);
        $this->uploads = new Uploads($this);
        $this->legal = new Legal($this);
        $this->agency = new Agency($this);
        $this->brand = new Brand($this);
    }

    // -- construction helpers -------------------------------------------------

    /** Create a client and log in with email/password. Base URL defaults to https://app.heroshots.ai. */
    public static function fromLogin(
        string $email,
        string $password,
        ?string $baseUrl = null,
        float $timeout = self::DEFAULT_TIMEOUT,
        bool $autoRelogin = true
    ): self {
        $client = new self($baseUrl, null, $email, $password, $autoRelogin, $timeout);
        $client->login($email, $password);
        return $client;
    }

    /** Create a client authenticated with a B2B API key (lz_live_...). */
    public static function withApiKey(string $apiKey, ?string $baseUrl = null, string|int|null $managedUserId = null): self
    {
        return new self($baseUrl, managedUserId: $managedUserId, apiKey: $apiKey);
    }

    /** True when the bearer is a B2B API key (lz_live_...), not a portal JWT. */
    public function usesApiKey(): bool
    {
        return $this->token !== null && str_starts_with($this->token, self::API_KEY_PREFIX);
    }

    // -- auth -----------------------------------------------------------------

    /** @return array<string,mixed> POST /api/auth/login -> stores and returns the JWT + profile. */
    public function login(string $email, string $password): array
    {
        $data = $this->request('POST', '/api/auth/login', ['json' => ['email' => $email, 'password' => $password], 'auth' => false]);
        $this->token = $data['token'] ?? null;
        $this->email = $email;
        $this->password = $password;
        return $data;
    }

    /**
     * Current account.
     *
     * With an API key: GET /api/v1/me ({user, credits_remaining}).
     * With a portal JWT: GET /api/auth/me (full portal profile).
     *
     * @return array<string,mixed>
     */
    public function me(): array
    {
        if ($this->usesApiKey()) {
            return $this->account->me();
        }
        return $this->request('GET', '/api/auth/me');
    }

    /** @return array<string,mixed> GET /api/auth/stats -> full raw portal stats (portal JWT only). */
    public function stats(): array
    {
        $stats = $this->request('GET', '/api/auth/stats');
        return is_array($stats) ? $stats : [];
    }

    /**
     * Compact credit/wallet view.
     *
     * With an API key it reads /api/v1/me and returns hs_credits, currency_credit
     * (the wallet in EUR, also as wallet_balance) and billing_type. With a portal
     * JWT it reads /api/auth/stats and also returns plan and usage counters.
     * Use stats() when you need the full raw stats payload.
     *
     * @return array<string,mixed>
     */
    public function balance(): array
    {
        if ($this->usesApiKey()) {
            $me = $this->account->me();
            $credits = is_array($me['credits_remaining'] ?? null) ? $me['credits_remaining'] : [];
            $user = is_array($me['user'] ?? null) ? $me['user'] : [];
            $out = [];
            if (array_key_exists('hs_credits', $credits)) {
                $out['hs_credits'] = $credits['hs_credits'];
            }
            $wallet = array_key_exists('currency_credit', $credits)
                ? $credits['currency_credit']
                : ($user['currency_credit'] ?? null);
            if ($wallet !== null) {
                $out['currency_credit'] = $wallet;
                $out['wallet_balance'] = $wallet;
            }
            if (array_key_exists('currency_billing_type', $user)) {
                $out['billing_type'] = $user['currency_billing_type'];
            }
            return $out;
        }

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

    /** Set the managed brand target for agency act-as requests (null clears it). */
    public function withManagedUser(string|int|null $managedUserId): self
    {
        $this->managedUserId = $managedUserId;
        return $this;
    }

    // -- transport ------------------------------------------------------------

    /**
     * Perform an HTTP request, returning decoded JSON or throwing an ApiException.
     *
     * Options: json (array), query (array; null values are dropped), multipart
     * (array), auth (bool, default true), unwrap (bool|null - inferred from the
     * path when null: /api/v1/* is unwrapped), raw (bool - return the body as a
     * string, for file downloads).
     *
     * HTTP 429 is retried up to $maxRetries times (Retry-After honoured, capped at
     * 60s; otherwise 1s, 2s, 4s... capped at 30s). Multipart requests are never
     * retried.
     *
     * @param array<string,mixed> $options
     * @return mixed
     */
    public function request(string $method, string $path, array $options = [], bool $retried = false): mixed
    {
        $auth = $options['auth'] ?? true;
        $unwrap = $options['unwrap'] ?? null;
        $raw = (bool) ($options['raw'] ?? false);

        $guzzle = [];
        if (isset($options['json'])) {
            // An empty PHP array would encode as "[]"; the API expects an object.
            $guzzle['json'] = $options['json'] === [] ? new \stdClass() : $options['json'];
        }
        if (isset($options['query']) && is_array($options['query'])) {
            $query = array_filter($options['query'], static fn ($v) => $v !== null);
            if ($query !== []) {
                $guzzle['query'] = $query;
            }
        }
        $multipart = isset($options['multipart']);
        if ($multipart) {
            $guzzle['multipart'] = $options['multipart'];
        }
        $headers = ['User-Agent' => 'heroshots-php/' . self::VERSION];
        if (!$raw) {
            $headers['Accept'] = 'application/json';
        }
        if ($auth && $this->token) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }
        if ($auth && $this->managedUserId !== null) {
            $headers['X-Managed-User-Id'] = (string) $this->managedUserId;
        }
        $guzzle['headers'] = $headers;

        $attempt = 0;
        while (true) {
            try {
                $resp = $this->http->request($method, $this->baseUrl . $path, $guzzle);
            } catch (TransferException $e) {
                throw new ApiException($e->getMessage(), 'network_error', null);
            }
            // 429 means the request was not processed: safe to retry, also for POST.
            // Multipart bodies are not retried (file streams are already consumed).
            if ($resp->getStatusCode() === 429 && $attempt < $this->maxRetries && !$multipart) {
                $attempt++;
                ($this->sleeper)(self::retryDelay($resp, $attempt));
                continue;
            }
            break;
        }

        $status = $resp->getStatusCode();
        $content = (string) $resp->getBody();

        if ($status < 200 || $status >= 300) {
            $body = $content === '' ? null : json_decode($content, true);
            $err = ApiException::fromResponse($status, is_array($body) ? $body : null);
            if ($err instanceof RateLimitException) {
                $err->retryAfter = self::retryAfterSeconds($resp);
            }
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

        if ($raw) {
            return $content;
        }

        $body = $content === '' ? null : json_decode($content, true);
        if ($unwrap === null) {
            $unwrap = str_starts_with($path, '/api/v1/');
        }
        if ($unwrap && is_array($body) && array_key_exists('data', $body)) {
            $data = $body['data'];
            if (is_array($data) && isset($body['meta']) && !isset($data['_meta'])) {
                // Surface billing/credits metadata without losing the shape.
                $data['_meta'] = $body['meta'];
            }
            return $data;
        }
        return $body;
    }

    /**
     * GET a binary endpoint. Returns the bytes, or writes $dest (creating parent
     * directories) and returns its path.
     *
     * @param array<string,mixed> $query
     */
    public function download(string $path, ?string $dest = null, array $query = []): string
    {
        $options = ['raw' => true];
        if ($query !== []) {
            $options['query'] = $query;
        }
        $content = $this->request('GET', $path, $options);
        if ($dest === null) {
            return $content;
        }
        $dir = dirname($dest);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new HeroShotsException("Cannot create directory {$dir}");
        }
        if (file_put_contents($dest, $content) === false) {
            throw new HeroShotsException("Cannot write {$dest}");
        }
        return $dest;
    }

    private static function retryAfterSeconds(ResponseInterface $resp): ?float
    {
        $value = trim($resp->getHeaderLine('Retry-After'));
        if ($value === '' || !is_numeric($value)) {
            return null;
        }
        return max(0.0, (float) $value);
    }

    private static function retryDelay(ResponseInterface $resp, int $attempt): float
    {
        $after = self::retryAfterSeconds($resp);
        if ($after !== null) {
            return min($after, 60.0);
        }
        return min(2.0 ** ($attempt - 1), 30.0);
    }
}
