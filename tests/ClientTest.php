<?php

declare(strict_types=1);

namespace HeroShots\Tests;

use GuzzleHttp\Client as Http;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use HeroShots\Client;
use HeroShots\Errors\NotFoundException;
use HeroShots\Errors\ProviderException;
use HeroShots\Errors\RateLimitException;
use HeroShots\Errors\ValidationException;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    /** @var array<int,array{request:Request}> */
    private array $history = [];

    /** @param Response[] $responses */
    private function client(array $responses): Client
    {
        return new Client('https://api.test', 'tok', null, null, false, 120.0, $this->mockHttp($responses));
    }

    /** @param Response[] $responses */
    private function mockHttp(array $responses): Http
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        return new Http(['handler' => $stack, 'http_errors' => false]);
    }

    /** @param array<string,mixed> $data */
    private function json(array $data, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($data));
    }

    public function testV1EnvelopeIsUnwrapped(): void
    {
        $client = $this->client([
            $this->json(['success' => true, 'data' => ['generation_id' => 'g1', 'status' => 'pending'], 'meta' => ['credits_remaining' => 5]]),
        ]);
        $out = $client->images->generate('https://x/p.jpg');
        $this->assertSame('g1', $out['generation_id']);
        $this->assertSame(5, $out['_meta']['credits_remaining']);
    }

    public function testBalanceReadsStats(): void
    {
        $client = $this->client([
            $this->json([
                'hs_credits' => 120,
                'total_credits_used' => 30,
                'current_month_generations' => 4,
                'plan' => ['wallet_balance' => 9.5, 'template_id' => 'free'],
                'recent_generations' => [['id' => 1]],
                'offered_plans' => [['id' => 1]],
            ]),
        ]);
        $bal = $client->balance();
        $this->assertSame(120, $bal['hs_credits']);
        $this->assertSame(9.5, $bal['plan']['wallet_balance']);
        $this->assertSame(9.5, $bal['wallet_balance']);
        $this->assertSame(4, $bal['current_month_generations']);
        $this->assertArrayNotHasKey('recent_generations', $bal);
        $this->assertArrayNotHasKey('offered_plans', $bal);
        $this->assertArrayNotHasKey('data', $bal);
    }

    public function testStatsReturnsRawAccountStats(): void
    {
        $client = $this->client([
            $this->json([
                'hs_credits' => 120,
                'plan' => ['wallet_balance' => 9.5],
                'recent_generations' => [['id' => 1]],
                'offered_plans' => [['id' => 1]],
            ]),
        ]);
        $stats = $client->stats();
        $this->assertSame(1, $stats['recent_generations'][0]['id']);
        $this->assertSame(1, $stats['offered_plans'][0]['id']);
    }

    public function testValidationErrorMapping(): void
    {
        $client = $this->client([
            $this->json(['success' => false, 'error' => ['code' => 'validation_error', 'message' => 'custom_prompt is required']], 400),
        ]);
        $this->expectException(ValidationException::class);
        $client->storyboard->candidates('', 30, 'https://x/p.jpg');
    }

    public function testNotFoundMapping(): void
    {
        $client = $this->client([
            $this->json(['success' => false, 'error' => ['code' => 'not_found', 'message' => 'expired']], 404),
        ]);
        $this->expectException(NotFoundException::class);
        $client->storyboard->status('missing');
    }

    public function testRateLimitMapping(): void
    {
        $client = $this->client([new Response(429, [], 'too many')]);
        $this->expectException(RateLimitException::class);
        $client->balance();
    }

    public function testUploadSendsMultipartFile(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'hs');
        file_put_contents($tmp, 'bytes');
        $client = $this->client([$this->json(['public_url' => 'https://cdn/x.jpg', 'filename' => 'x.jpg', 'folder' => 'products'])]);
        $out = $client->uploads->image($tmp, 'products');
        $this->assertSame('https://cdn/x.jpg', $out['public_url']);
        $body = (string) $this->history[0]['request']->getBody();
        $this->assertStringContainsString('name="file"', $body);
        $this->assertStringContainsString('name="folder"', $body);
    }

    public function testStoryboardWaitSucceedsOnDone(): void
    {
        $client = $this->client([
            $this->json(['job_id' => 'j1', 'status' => 'queued']),
            $this->json(['job_id' => 'j1', 'status' => 'done', 'storyboard' => ['segments' => []]]),
        ]);
        $out = $client->storyboard->wait('j1', 0.0, 5.0);
        $this->assertSame('done', $out['status']);
    }

    public function testWaitRaisesProviderErrorOnFailed(): void
    {
        $client = $this->client([
            $this->json(['job_id' => 9, 'status' => 'failed', 'error' => ['code' => 'provider_error', 'message' => 'boom']]),
        ]);
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('boom');
        $client->longvideo->wait(9, 0.0, 5.0);
    }

    public function testVideoWaitUsesSucceededTerminal(): void
    {
        $client = $this->client([
            $this->json(['task_id' => 't1', 'status' => 'succeeded', 'video_url' => 'https://cdn/v.mp4']),
        ]);
        $out = $client->videos->wait('t1', 0.0, 5.0);
        $this->assertStringEndsWith('v.mp4', $out['video_url']);
    }

    public function testAvatarsListUsesV1(): void
    {
        $client = $this->client([
            $this->json(['success' => true, 'data' => ['avatars' => [['id' => 1]]], 'meta' => []]),
        ]);
        $out = $client->avatars->list();
        $this->assertCount(1, $out['avatars']);
        $this->assertStringContainsString('/api/v1/avatar/list', (string) $this->history[0]['request']->getUri());
    }

    public function testAutoReloginOnTokenExpired(): void
    {
        $client = new Client(
            'https://api.test',
            'stale',
            'a@b.com',
            'pw',
            true,
            120.0,
            $this->mockHttp([
                $this->json(['success' => false, 'error' => ['code' => 'token_expired', 'message' => 'expired']], 401),
                $this->json(['token' => 'fresh']),
                $this->json(['user_id' => 1]),
            ])
        );
        $out = $client->me();
        $this->assertSame(1, $out['user_id']);
        $this->assertSame('fresh', $client->token);
    }
}
