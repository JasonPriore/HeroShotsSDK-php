<?php

declare(strict_types=1);

namespace HeroShots\Errors;

/**
 * Base exception for every error returned by the HeroShots API.
 *
 * The backend returns {"success": false, "error": {"code", "message", "details"}}.
 * {@see ApiException::fromResponse()} maps the code to a specific subclass.
 */
class ApiException extends HeroShotsException
{
    public ?string $errorCode;
    public ?int $status;
    /** @var array<string,mixed> */
    public array $details;

    /** @param array<string,mixed> $details */
    public function __construct(string $message, ?string $code = null, ?int $status = null, array $details = [])
    {
        parent::__construct($message);
        $this->errorCode = $code;
        $this->status = $status;
        $this->details = $details;
    }

    /**
     * Build the most specific exception for an HTTP error response.
     *
     * @param array<string,mixed>|null $body
     */
    public static function fromResponse(int $status, ?array $body): ApiException
    {
        $code = null;
        $message = null;
        $details = [];
        if (is_array($body)) {
            $err = $body['error'] ?? null;
            if (is_array($err)) {
                $code = $err['code'] ?? null;
                $message = $err['message'] ?? null;
                $details = is_array($err['details'] ?? null) ? $err['details'] : [];
            } elseif (is_string($err)) {
                $message = $err;
            }
            $message = $message ?? ($body['message'] ?? null);
        }

        $class = self::classFor($status, $code);
        return new $class($message ?? "HTTP {$status}", $code, $status, $details);
    }

    private static function classFor(int $status, ?string $code): string
    {
        static $map = [
            'missing_api_key' => AuthException::class,
            'missing_authorization' => AuthException::class,
            'missing_bearer_token' => AuthException::class,
            'invalid_api_key' => AuthException::class,
            'invalid_token' => AuthException::class,
            // Portal routes report an expired login this way: relogin once, like token_expired.
            'invalid_or_expired_token' => TokenExpiredException::class,
            'unauthorized' => AuthException::class,
            'user_not_found' => AuthException::class,
            'token_expired' => TokenExpiredException::class,
            'validation_error' => ValidationException::class,
            'not_found' => NotFoundException::class,
            'resource_gone' => NotFoundException::class,
            'forbidden' => ForbiddenException::class,
            'plan_not_allowed' => ForbiddenException::class,
            'insufficient_scope' => ForbiddenException::class,
            'conflict' => ConflictException::class,
            'plan_change_locked' => ConflictException::class,
            'quota_exceeded' => QuotaExceededException::class,
            'payload_too_large' => PayloadTooLargeException::class,
            'provider_error' => ProviderException::class,
            'service_unavailable' => ServiceUnavailableException::class,
            'pricing_not_configured' => ServiceUnavailableException::class,
            'stripe_not_configured' => ServiceUnavailableException::class,
            'no_payment_method' => BillingException::class,
            'wallet_vat_validation_required' => BillingException::class,
            'internal_error' => InternalException::class,
        ];

        if ($status === 429) {
            return RateLimitException::class;
        }
        if ($code !== null && isset($map[$code])) {
            return $map[$code];
        }
        if ($status >= 500) {
            return InternalException::class;
        }
        if ($status === 404) {
            return NotFoundException::class;
        }
        if ($status === 401 || $status === 403) {
            return AuthException::class;
        }
        return ApiException::class;
    }
}
