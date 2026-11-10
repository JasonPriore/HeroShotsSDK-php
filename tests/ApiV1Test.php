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
use HeroShots\Errors\BillingException;
use HeroShots\Errors\ConflictException;
use HeroShots\Errors\ForbiddenException;
use HeroShots\Errors\HeroShotsException;
use HeroShots\Errors\RateLimitException;
use PHPUnit\Framework\TestCase;

/** Public API (/api/v1) coverage: API-key auth, new resources, downloads, retries. */
final class ApiV1Test extends TestCase
{
    private const BASE = 'https://api.test';
    private const KEY = 'lz_live_test';

    /** @var array<int,array{request:Request}> */
    private array $history = [];
    /** @var float[] */
    private array $sleeps = [];

    /** @param Response[] $responses */
    private function mockHttp(array $responses): Http
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        return new Http(['handler' => $stack, 'http_errors' => false]);
    }

    /** @param Response[] $responses */
    private function apiClient(array $responses, int $maxRetries = 2, string|int|null $managedUserId = null): Client
    {
        $client = new Client(
            self::BASE,
            autoRelogin: false,
            http: $this->mockHttp($responses),
            managedUserId: $managedUserId,
            maxRetries: $maxRetries,
            apiKey: self::KEY
        );
        $this->sleeps = [];
        $client->sleeper = function (float $s): void {
            $this->sleeps[] = $s;
        };
        return $client;
    }

    /** @param Response[] $responses */
    private function jwtClient(array $responses): Client
    {
        return new Client(self::BASE, 'jwt', null, null, false, 120.0, $this->mockHttp($responses));
    }

    /** @param array<string,mixed> $data */
    private function json(array $data, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($data));
    }

    /**
     * @param mixed $data
     * @param array<string,mixed>|null $meta
     */
    private function ok(mixed $data, ?array $meta = null, int $status = 200): Response
    {
        $body = ['success' => true, 'data' => $data];
        if ($meta !== null) {
            $body['meta'] = $meta;
        }
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }

    private function req(int $i = -1): Request
    {
        $i = $i < 0 ? count($this->history) + $i : $i;
        return $this->history[$i]['request'];
    }

    /** @return mixed */
    private function lastJson(): mixed
    {
        return json_decode((string) $this->req()->getBody(), true);
    }

    private function lastRawBody(): string
    {
        return (string) $this->req()->getBody();
    }

    private function tmpDir(): string
    {
        $dir = sys_get_temp_dir() . '/heroshots-php-' . bin2hex(random_bytes(4));
        mkdir($dir);
        return $dir;
    }

    // -- auth / transport -----------------------------------------------------

    public function testApiKeyIsSentAsBearerWithUserAgent(): void
    {
        $client = $this->apiClient([$this->ok(['user' => ['id' => 1], 'credits_remaining' => ['hs_credits' => 3]])]);
        $this->assertTrue($client->usesApiKey());
        $out = $client->me();
        $req = $this->req();
        $this->assertSame('/api/v1/me', $req->getUri()->getPath());
        $this->assertSame('Bearer ' . self::KEY, $req->getHeaderLine('Authorization'));
        $this->assertSame('heroshots-php/' . Client::VERSION, $req->getHeaderLine('User-Agent'));
        $this->assertSame('0.3.0', Client::VERSION);
        $this->assertSame('application/json', $req->getHeaderLine('Accept'));
        $this->assertSame(1, $out['user']['id']);
    }

    public function testJwtMeUsesPortalRoute(): void
    {
        $client = $this->jwtClient([$this->json(['user_id' => 7])]);
        $this->assertFalse($client->usesApiKey());
        $client->me();
        $this->assertSame('/api/auth/me', $this->req()->getUri()->getPath());
    }

    public function testApiKeyFromEnv(): void
    {
        $prev = getenv('HEROSHOTS_API_KEY');
        putenv('HEROSHOTS_API_KEY=lz_live_env');
        try {
            $this->assertSame('lz_live_env', (new Client(self::BASE))->token);
            // An explicit token / apiKey wins over the environment.
            $this->assertSame('jwt', (new Client(self::BASE, 'jwt'))->token);
            $this->assertSame('lz_live_x', (new Client(self::BASE, apiKey: 'lz_live_x'))->token);
        } finally {
            putenv($prev === false ? 'HEROSHOTS_API_KEY' : "HEROSHOTS_API_KEY={$prev}");
        }
    }

    public function testWithApiKeyHelper(): void
    {
        $client = Client::withApiKey('lz_live_abc', 'https://api.test/', 55);
        $this->assertSame('lz_live_abc', $client->token);
        $this->assertSame('https://api.test', $client->baseUrl);
        $this->assertSame(55, $client->managedUserId);
        $this->assertTrue($client->usesApiKey());
    }

    public function testBalanceWithApiKeyReadsV1Me(): void
    {
        $client = $this->apiClient([$this->ok([
            'user' => ['id' => 1, 'currency_credit' => 50.0, 'currency_billing_type' => 'debit'],
            'credits_remaining' => ['hs_credits' => 10, 'currency_credit' => 50.0],
        ])]);
        $out = $client->balance();
        $this->assertSame('/api/v1/me', $this->req()->getUri()->getPath());
        // assertEquals: json_encode(50.0) emits 50 in the mocked body.
        $this->assertEquals(
            ['hs_credits' => 10, 'currency_credit' => 50.0, 'wallet_balance' => 50.0, 'billing_type' => 'debit'],
            $out
        );
    }

    public function testFreePlanApiKeyMapsToForbidden(): void
    {
        $client = $this->apiClient([$this->json(['success' => false, 'error' => [
            'code' => 'plan_not_allowed', 'message' => 'API access is not available on the Free plan.',
        ]], 403)]);
        try {
            $client->account->me();
            $this->fail('ForbiddenException not thrown');
        } catch (ForbiddenException $e) {
            $this->assertSame('plan_not_allowed', $e->errorCode);
        }
    }

    public function testVatValidationMapsToBillingException(): void
    {
        $client = $this->apiClient([$this->json(['success' => false, 'error' => [
            'code' => 'wallet_vat_validation_required', 'message' => 'VAT',
        ]], 402)]);
        $this->expectException(BillingException::class);
        $client->videos->generate('https://x/p.jpg', ['duration_seconds' => 15]);
    }

    public function testNoPaymentMethodMapsToBillingAndPlanChangeLockedToConflict(): void
    {
        $client = $this->apiClient([
            $this->json(['success' => false, 'error' => ['code' => 'no_payment_method', 'message' => 'x']], 402),
            $this->json(['success' => false, 'error' => ['code' => 'plan_change_locked', 'message' => 'x']], 409),
        ]);
        try {
            $client->pricing->catalog();
            $this->fail('BillingException not thrown');
        } catch (BillingException $e) {
            $this->assertSame('no_payment_method', $e->errorCode);
        }
        $this->expectException(ConflictException::class);
        $client->pricing->catalog();
    }

    public function testRateLimitIsRetriedThenSucceeds(): void
    {
        $client = $this->apiClient([
            new Response(429, ['Retry-After' => '1']),
            $this->ok(['status' => 'ok']),
        ]);
        $out = $client->account->health();
        $this->assertSame('ok', $out['status']);
        $this->assertCount(2, $this->history);
        $this->assertSame([1.0], $this->sleeps);
        $this->assertSame('', $this->req()->getHeaderLine('Authorization'));
    }

    public function testRateLimitBackoffWithoutRetryAfterAndCap(): void
    {
        $client = $this->apiClient([
            new Response(429),
            new Response(429, ['Retry-After' => '120']),
            new Response(429, ['Retry-After' => '3']),
        ]);
        try {
            $client->account->health();
            $this->fail('RateLimitException not thrown');
        } catch (RateLimitException $e) {
            $this->assertSame(3.0, $e->retryAfter);
        }
        $this->assertCount(3, $this->history);
        // attempt 1: no header -> 2^0 = 1s; attempt 2: Retry-After 120 capped at 60s.
        $this->assertSame([1.0, 60.0], $this->sleeps);
    }

    public function testMultipartIsNeverRetried(): void
    {
        $client = new Client(self::BASE, 'jwt', null, null, false, 120.0, $this->mockHttp([new Response(429)]));
        $client->sleeper = function (float $s): void {
            $this->fail('multipart request must not be retried');
        };
        $this->expectException(RateLimitException::class);
        try {
            $client->uploads->image('bytes', 'products');
        } finally {
            $this->assertCount(1, $this->history);
        }
    }

    public function testLocalPathWithApiKeyRaisesClearError(): void
    {
        $img = tempnam(sys_get_temp_dir(), 'hs');
        file_put_contents($img, 'x');
        $client = $this->apiClient([]);
        try {
            $client->images->generate($img);
            $this->fail('HeroShotsException not thrown');
        } catch (HeroShotsException $e) {
            $this->assertStringContainsString('public https', $e->getMessage());
            $this->assertStringContainsString('Cannot upload local file', $e->getMessage());
        } finally {
            @unlink($img);
        }
        $this->assertCount(0, $this->history);
    }

    public function testUrlsAndDataUrisPassThroughAndExtraFieldsAreKept(): void
    {
        $client = $this->apiClient([$this->ok(['generation_id' => 'g'], null, 202)]);
        $client->images->generate('https://x/p.jpg', null, [
            'background_image_url' => 'data:image/png;base64,AAAA',
            'additional_product_image_urls' => ['https://x/a.jpg', 'https://x/b.jpg'],
            'avatar_id' => 3,
        ]);
        $body = $this->lastJson();
        $this->assertSame('https://x/p.jpg', $body['product_image_url']);
        $this->assertSame('data:image/png;base64,AAAA', $body['background_image_url']);
        $this->assertSame(['https://x/a.jpg', 'https://x/b.jpg'], $body['additional_product_image_urls']);
        $this->assertArrayNotHasKey('subject_image_url', $body);
    }

    public function testNullQueryValuesAreDroppedAndEmptyJsonIsAnObject(): void
    {
        $client = $this->apiClient([$this->ok(['avatars' => []]), $this->ok([])]);
        $client->avatars->list(['gender' => 'female', 'q' => null]);
        $this->assertSame('gender=female', $this->req()->getUri()->getQuery());
        $client->avatars->restoreOutfit(3);
        $this->assertSame('{}', $this->lastRawBody());
    }

    // -- downloads --------------------------------------------------------------

    public function testImageDownloadReturnsBytesAndVideoDownloadWritesFile(): void
    {
        $client = $this->apiClient([
            new Response(200, ['Content-Type' => 'image/jpeg'], "\xff\xd8jpeg"),
            new Response(200, ['Content-Type' => 'video/mp4'], 'mp4data'),
        ]);
        $this->assertSame("\xff\xd8jpeg", $client->images->download(42));
        $this->assertSame('/api/v1/image/42/download', $this->req()->getUri()->getPath());
        $this->assertSame('', $this->req()->getHeaderLine('Accept'));

        $dest = $this->tmpDir() . '/out/v.mp4';
        $saved = $client->videos->download(43, $dest);
        $this->assertSame($dest, $saved);
        $this->assertSame('mp4data', file_get_contents($saved));
        $this->assertSame('/api/v1/video/43/download', $this->req()->getUri()->getPath());
    }

    // -- route changes ------------------------------------------------------------

    public function testVideoStatusUsesV1AndProviderParam(): void
    {
        $client = $this->apiClient([$this->ok(['status' => 'processing'])]);
        $out = $client->videos->status('t9', 'byteplus');
        $this->assertSame('processing', $out['status']);
        $this->assertStringEndsWith('/api/v1/video/status/t9?provider=byteplus', (string) $this->req()->getUri());
    }

    public function testVideoGenerateResolvesProductAndSubjectUrls(): void
    {
        $client = $this->apiClient([$this->ok(['video_id' => 1], null, 202)]);
        $client->videos->generate(null, [
            'product_image_url' => 'https://x/p.jpg',
            'subject_image_url' => 'https://x/s.jpg',
            'duration_seconds' => 15,
        ]);
        $this->assertSame(
            ['product_image_url' => 'https://x/p.jpg', 'subject_image_url' => 'https://x/s.jpg', 'duration_seconds' => 15],
            $this->lastJson()
        );
    }

    public function testLongVideoStartAndRegenerateSegment(): void
    {
        $client = $this->apiClient([
            $this->ok(['job_id' => 5, 'status' => 'queued'], null, 202),
            $this->ok(['job_id' => 5]),
        ]);
        $job = $client->longvideo->start('https://x/p.jpg', 'spot', durationSeconds: 30);
        $this->assertSame(5, $job['job_id']);
        $this->assertSame('/api/v1/long-video/start', $this->req(0)->getUri()->getPath());
        $this->assertSame(
            ['ratio' => '16:9', 'resolution' => '1080p', 'image_url' => 'https://x/p.jpg', 'custom_prompt' => 'spot', 'duration_seconds' => 30],
            json_decode((string) $this->req(0)->getBody(), true)
        );

        $client->longvideo->regenerateSegment(5, 1, 'slower', true);
        $this->assertSame('/api/v1/long-video/5/segments/1/regenerate', $this->req()->getUri()->getPath());
        $this->assertSame(['comment' => 'slower', 'cascade' => true], $this->lastJson());
    }

    public function testStillLifeStartUsesV1(): void
    {
        $client = $this->apiClient([$this->ok(['task_id' => 8], null, 202), $this->ok(['status' => 'processing'])]);
        $out = $client->stilllife->start('https://x/p.jpg', 'https://x/bg.jpg', 'slow orbit');
        $this->assertSame(8, $out['task_id']);
        $this->assertSame('/api/v1/stilllife-video/start', $this->req()->getUri()->getPath());
        $this->assertSame('slow orbit', $this->lastJson()['custom_prompt']);
        $this->assertArrayNotHasKey('detail_image_url', $this->lastJson());
        $this->assertArrayNotHasKey('reference_video_url', $this->lastJson());

        $client->stilllife->status(8);
        $this->assertSame('/api/v1/stilllife-video/status/8', $this->req()->getUri()->getPath());
    }

    public function testStillLifeDetailAndReferenceVideo(): void
    {
        $client = $this->apiClient([$this->ok(['task_id' => 8], null, 202)]);
        $client->stilllife->start('https://x/p.jpg', 'https://x/bg.jpg', detailImageUrl: 'https://x/d.jpg', referenceVideoUrl: 'https://x/r.mp4');
        $body = $this->lastJson();
        $this->assertSame('https://x/d.jpg', $body['detail_image_url']);
        $this->assertSame('https://x/r.mp4', $body['reference_video_url']);
        $this->assertArrayNotHasKey('custom_prompt', $body);
    }

    // -- new resources --------------------------------------------------------------

    public function testGenerationsIterateWalksPages(): void
    {
        $client = $this->apiClient([
            $this->ok(['generations' => [['id' => 1]], 'page' => 1, 'pages' => 2]),
            $this->ok(['generations' => [['id' => 2]], 'page' => 2, 'pages' => 2]),
        ]);
        $ids = [];
        foreach ($client->generations->iterate(['type' => 'video'], 1) as $g) {
            $ids[] = $g['id'];
        }
        $this->assertSame([1, 2], $ids);
        $this->assertStringContainsString('type=video', $this->req(0)->getUri()->getQuery());
        $this->assertStringContainsString('page=2', $this->req(1)->getUri()->getQuery());
        $this->assertStringContainsString('per_page=1', $this->req(1)->getUri()->getQuery());
    }

    public function testPricingQuoteAndCatalog(): void
    {
        $client = $this->apiClient([$this->ok(['total_eur' => '15.00']), $this->ok(['video' => []])]);
        $this->assertSame('15.00', $client->pricing->quote('video', ['duration_seconds' => 15])['total_eur']);
        $this->assertSame(['call_type' => 'video', 'duration_seconds' => 15], $this->lastJson());
        $this->assertArrayHasKey('video', $client->pricing->catalog());
    }

    public function testUsageRightsQuoteGetAndPost(): void
    {
        $client = $this->apiClient([$this->json(['delta_eur' => '3.00']), $this->json(['delta_eur' => '5.00'])]);
        $a = $client->images->usageRightsQuote(7, null, 'paid_digital', '6_months');
        $this->assertSame('3.00', $a['delta_eur']);
        $this->assertSame('GET', $this->req(0)->getMethod());
        $this->assertStringContainsString('usage_rights_type=paid_digital', (string) $this->req(0)->getUri());
        $b = $client->images->usageRightsQuote(7, [['type' => 'broadcast', 'duration' => '2_years']]);
        $this->assertSame('5.00', $b['delta_eur']);
        $this->assertSame('POST', $this->req()->getMethod());
        $this->assertSame(['usage_rights' => [['type' => 'broadcast', 'duration' => '2_years']]], $this->lastJson());
    }

    public function testAvatarEditEndpoints(): void
    {
        $client = $this->apiClient([
            $this->json(['success' => true]),
            $this->ok(['mode' => 'generated']),
            $this->ok([], null, 202),
            $this->json(['success' => true], 202),
        ]);
        $client->avatars->update(3, ['name' => 'Giulia']);
        $this->assertSame('PATCH', $this->req()->getMethod());
        $this->assertSame(['name' => 'Giulia'], $this->lastJson());
        $client->avatars->setImageSet(3, 'generated');
        $this->assertSame(['mode' => 'generated'], $this->lastJson());
        $client->avatars->changeOutfit(3, [['slot' => 'top', 'url' => 'https://x/t.jpg']]);
        $this->assertSame(['items' => [['slot' => 'top', 'url' => 'https://x/t.jpg']]], $this->lastJson());
        $client->avatars->regenerateView(3, 'closeup');
        $this->assertSame('/api/v1/avatar/3/regenerate-view', $this->req()->getUri()->getPath());
        $this->assertSame(['view_type' => 'closeup'], $this->lastJson());
    }

    public function testBulkUploadImagesIsMultipartAndWaitReturnsFinal(): void
    {
        $client = $this->apiClient([
            $this->json(['uploaded' => 2, 'items' => []], 201),
            $this->json(['id' => 'b1', 'status' => 'processing']),
            $this->json(['id' => 'b1', 'status' => 'partially_completed']),
        ]);
        $out = $client->bulk->uploadImages('b1', ['one', 'two']);
        $this->assertSame(2, $out['uploaded']);
        $this->assertSame(2, substr_count($this->lastRawBody(), 'name="files"'));
        $final = $client->bulk->wait('b1', 0.0, 5.0);
        $this->assertSame('partially_completed', $final['status']);
        $this->assertCount(3, $this->history);
    }

    public function testBulkDownloadGeneratedFiltersItems(): void
    {
        $client = $this->apiClient([new Response(200, ['Content-Type' => 'application/zip'], 'PK')]);
        $this->assertSame('PK', $client->bulk->downloadGenerated('b1', null, [1, 2]));
        $this->assertStringEndsWith('item_ids=1%2C2', (string) $this->req()->getUri());
    }

    public function testAssetCreateIsMultipart(): void
    {
        $client = $this->apiClient([$this->json(['id' => 9, 'name' => 'Bag'], 201)]);
        $out = $client->assets->create('Bag', 'img', 4);
        $this->assertSame(9, $out['id']);
        $body = $this->lastRawBody();
        $this->assertStringContainsString('name="primary_image"', $body);
        $this->assertStringContainsString('name="category_id"', $body);
        $this->assertStringNotContainsString('name="product_description"', $body);
    }

    public function testCollectionCreateJsonWithoutCoverAndMultipartWithCover(): void
    {
        $client = $this->apiClient([$this->json(['id' => 1]), $this->json(['id' => 2])]);
        $client->collections->create('Summer');
        $this->assertSame(['name' => 'Summer'], $this->lastJson());
        $client->collections->create('Winter', 'cold', fopen('php://memory', 'rb'));
        $this->assertStringContainsString('name="cover"', $this->lastRawBody());
        $this->assertStringContainsString('name="description"', $this->lastRawBody());
    }

    public function testCollectionsAndCategories(): void
    {
        $client = $this->apiClient([$this->ok([]), $this->ok([])]);
        $client->collections->addGeneration(2, '15');
        $this->assertSame(['log_id' => 15], $this->lastJson());
        $client->categories->setSelected(['1', 2]);
        $this->assertSame(['category_ids' => [1, 2]], $this->lastJson());
    }

    public function testAgencyActAsAndTopup(): void
    {
        $client = $this->apiClient([
            $this->json(['ok' => true]),
            $this->ok(['generation_id' => 'g'], null, 202),
        ]);
        $client->agency->topup(4, 25.0);
        $this->assertSame('/api/v1/agency/managed-clients/4/topup', $this->req()->getUri()->getPath());
        $this->assertEquals(['amount' => 25.0], $this->lastJson());
        $client->withManagedUser(77)->images->generate('https://x/p.jpg', null, ['avatar_id' => 1]);
        $this->assertSame('77', $this->req()->getHeaderLine('X-Managed-User-Id'));
    }

    public function testAgencyAndBrandTermination(): void
    {
        $client = $this->apiClient([$this->json(['ok' => true]), $this->json(['ok' => true]), $this->json(['ok' => true])]);
        $client->agency->respondTermination(4, false, 'not now');
        $this->assertSame(['accept' => false, 'response_reason' => 'not now'], $this->lastJson());
        $client->brand->respondTermination(true);
        $this->assertSame('/api/v1/brand/manager-relationship/termination/respond', $this->req()->getUri()->getPath());
        $this->assertSame(['accept' => true], $this->lastJson());
        $client->agency->resendInvite(4);
        $this->assertSame('', $this->lastRawBody());
    }

    public function testLegalTemplatesDownloadAndAttach(): void
    {
        $client = $this->jwtClient([new Response(200, [], 'DOCX'), $this->json(['ok' => true])]);
        $this->assertSame('DOCX', $client->legal->liberatoriaTemplate());
        $this->assertSame('/api/legal/byo/liberatoria-template', $this->req()->getUri()->getPath());
        $client->legal->attachLiberatoria(10, 3, 'r', 'i', 'm@x.com', '1990-01-01');
        $this->assertSame('/api/legal/byo/10/avatars', $this->req()->getUri()->getPath());
        $this->assertSame(3, $this->lastJson()['avatar_id']);
    }

    public function testUploadsAcceptBytesAndResources(): void
    {
        $client = $this->jwtClient([
            $this->json(['public_url' => 'https://cdn/a.mp3']),
            $this->json(['public_url' => 'https://cdn/c.pdf']),
        ]);
        $stream = fopen('php://memory', 'r+b');
        fwrite($stream, 'audio-bytes');
        rewind($stream);
        $client->uploads->referenceAudio($stream);
        $this->assertSame('/upload/reference-audio', $this->req()->getUri()->getPath());
        $this->assertStringContainsString('audio-bytes', $this->lastRawBody());

        $client->uploads->consentDocument('%PDF-1.4 bytes');
        $body = $this->lastRawBody();
        $this->assertStringContainsString('avatar-consents', $body);
        $this->assertStringContainsString('%PDF-1.4 bytes', $body);
    }

    public function testMissingFilePathFailsLoudly(): void
    {
        $client = $this->jwtClient([]);
        $this->expectException(HeroShotsException::class);
        $this->expectExceptionMessage('File not found');
        $client->uploads->image('/nope/missing.jpg');
    }
}
