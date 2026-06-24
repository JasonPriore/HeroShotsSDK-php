<?php

declare(strict_types=1);

namespace HeroShots;

use HeroShots\Errors\ProviderException;
use HeroShots\Errors\ApiException;

/**
 * Generic async-job polling helper.
 *
 * Terminal-state names differ across surfaces: storyboard/long-video jobs use
 * "done"; single video/image status uses "succeeded". Each resource passes its
 * own state sets.
 */
final class Polling
{
    /** @var string[] */
    public const STORYBOARD_OK = ['done', 'succeeded', 'completed'];
    /** @var string[] */
    public const STORYBOARD_FAIL = ['failed', 'error', 'cancelled', 'canceled'];
    /** @var string[] */
    public const MEDIA_OK = ['succeeded', 'done', 'completed'];
    /** @var string[] */
    public const MEDIA_FAIL = ['failed', 'error', 'cancelled', 'canceled'];

    /**
     * @param callable():array<string,mixed> $getFn
     * @param string[] $terminalOk
     * @param string[] $terminalFail
     * @param callable(array<string,mixed>):void|null $onProgress
     * @return array<string,mixed>
     */
    public static function wait(
        callable $getFn,
        array $terminalOk = self::STORYBOARD_OK,
        array $terminalFail = self::STORYBOARD_FAIL,
        string $statusKey = 'status',
        float $interval = 3.0,
        float $timeout = 600.0,
        ?callable $onProgress = null
    ): array {
        $ok = array_map('strtolower', $terminalOk);
        $fail = array_map('strtolower', $terminalFail);
        $deadline = microtime(true) + $timeout;
        while (true) {
            $payload = $getFn();
            if ($onProgress !== null) {
                $onProgress($payload);
            }
            $status = strtolower((string) ($payload[$statusKey] ?? ''));
            if (in_array($status, $ok, true)) {
                return $payload;
            }
            if (in_array($status, $fail, true)) {
                $err = $payload['error'] ?? null;
                if (is_array($err)) {
                    throw new ProviderException(
                        $err['message'] ?? "job {$status}",
                        $err['code'] ?? 'provider_error',
                        null,
                        $err
                    );
                }
                throw new ProviderException(is_string($err) && $err !== '' ? $err : "job {$status}", 'provider_error');
            }
            if (microtime(true) >= $deadline) {
                throw new ApiException("job did not finish within {$timeout}s (last status={$status})", 'timeout');
            }
            if ($interval > 0) {
                usleep((int) ($interval * 1_000_000));
            }
        }
    }
}
