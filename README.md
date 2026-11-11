# heroshots/sdk (PHP SDK)

PHP client for the HeroShots / StudioHero API. Works with a **B2B API key**
(`lz_live_...`) or a **portal login (JWT)** and covers the whole public
`/api/v1` surface plus the StudioHero storyboard flow. PSR-4 namespace
`HeroShots\`. See [`GUIDE.md`](./GUIDE.md) for use-case walkthroughs (Italian)
and [`CHANGELOG.md`](./CHANGELOG.md) for changes per version.

## Install

```bash
composer require heroshots/sdk
```

Requires PHP 8.1+. Runtime dependency: `guzzlehttp/guzzle`.

## Authenticate

```php
use HeroShots\Client;

// API key (server-to-server). Also read from HEROSHOTS_API_KEY.
$client = Client::withApiKey('lz_live_...');
$client = new Client(apiKey: 'lz_live_...');    // same thing

// Portal login: stores the JWT and logs in again automatically when it expires.
$client = Client::fromLogin('dev@partner.com', 'secret');

// Or reuse an existing JWT.
$client = new Client(token: 'eyJ...');
```

Base URL defaults to `https://app.heroshots.ai` (override with the first
constructor argument or `HEROSHOTS_BASE_URL`).

| Endpoints | API key | Portal JWT |
|---|---|---|
| `/api/v1/*`: images, videos, long video, still life, avatars, bulk, library, generations, pricing, agency, brand | ✅ | ✅ |
| Storyboard (StudioHero), uploads, image candidates, long-video export (final cut), still-life background edits, BYO legal documents | ❌ | ✅ |

- API keys are **refused on the Free plan** (`ForbiddenException`, code
  `plan_not_allowed`), except for agency accounts.
- **Local file paths** are uploaded automatically only with a portal JWT. With
  an API key pass public `https://` URLs (or `data:` URIs): the SDK throws a
  `HeroShotsException` otherwise, before sending anything.
- `$client->usesApiKey()` tells which credential the client holds.

## Quick start

```php
$client = Client::withApiKey('lz_live_...');

print_r($client->balance());                  // ['hs_credits' => 10, 'wallet_balance' => 50.0, ...]
print_r($client->pricing->quote('video', ['duration_seconds' => 15]));

$job = $client->videos->generate(null, [
    'product_image_url' => 'https://example.com/product.jpg',
    'avatar_id' => 42,
    'custom_prompt' => 'slow dolly-in, soft studio light',
    'duration_seconds' => 15,
]);
$done = $client->videos->wait($job['video_id']);
echo $done['video_url'];
```

Extra backend fields go in the trailing `$payload` / `$fields` / `$query` array
argument and are forwarded verbatim (null query values are dropped). `/api/v1`
billing metadata (charges, credits left) is under `$result['_meta']`.

## Resources

| Property | What it does |
|---|---|
| `$client->account` | `me()`, `health()` |
| `$client->images` | `generate`, `status`, `wait`, `download`, `generateBackground`, `usageRightsQuote`, `usageRightsUpgrade`, `analyzeEditRegion`, `candidates`, `selectCandidate` |
| `$client->videos` | `generate`, `status`, `wait`, `download` |
| `$client->longvideo` | `start`, `status`, `wait` (until `segments_ready`), `regenerateSegment`, `export`, `render` (final cut, portal JWT), `view` |
| `$client->stilllife` | `start`, `status`, `wait`, `previewBackground`, `changeBackground`, `changeBackgroundStatus` |
| `$client->storyboard` | `briefSuggestions`, `candidates`, `generate`, `frames`, `revise`, `status`, `wait`, `subjectMeasurements`, `analyzeProduct`, `creativity` |
| `$client->avatars` | `list`, `get`, `generate`, `createFromSteps`, `update`, `delete`, `uploadView`, `regenerateView`, `regenerateVariants`, `generatedVersion`, `setImageSet`, `generateSheet`, `generateVoice`, `resolveOutfitLink`, `changeOutfit`, `restoreOutfit`, `improvePrompt`, `analyzeAttributes`, `consentDocument` (+ `Avatars::VIEW_TYPES`) |
| `$client->bulk` | `list`, `create`, `get`, `update`, `delete`, `uploadImages`, `start`, `wait`, `regenerateItem`, `regenerateAll`, `cancelItem`, `removeItem`, `replaceProduct`, `previews`, `attempts`, `selectAttempt`, `downloadItem`, `downloadProduct`, `downloadGenerated` |
| `$client->generations` | `list`, `iterate` (a `Generator` over every page) |
| `$client->pricing` | `catalog`, `quote`, `videoPackagesCatalog`, `videoPackagesQuote`, `usageRightsRules` |
| `$client->assets` · `$client->collections` · `$client->backgrounds` · `$client->categories` | product library (CRUD; multipart when a file is given) |
| `$client->uploads` | `image`, `consentDocument`, `referenceAudio` |
| `$client->legal` | `byoStatus`, `manlevaTemplate`, `liberatoriaTemplate`, `uploadManleva`, `attachLiberatoria` |
| `$client->agency` | managed brands: `clients`, `client`, `inviteClient`, `createClient`, `updateClient`, `topup`, `resendInvite`, `claimInvite`, `requestTermination`, `respondTermination`, `cancelTermination` |
| `$client->brand` | the brand side of an agency relationship: `acceptManagerInvite`, `rejectManagerInvite`, `managerRelationship`, `managerRelationships`, `requestTermination`, `respondTermination`, `cancelTermination` |

On the client: `login()`, `me()`, `balance()`, `stats()`, `withManagedUser()`,
`usesApiKey()`, `download()`, `request()`.

Every method maps 1:1 to an endpoint. Files (uploads, bulk images, asset
photos, collection covers, legal documents) can be a **path**, a **string of
bytes** or an open **stream resource**.

## StudioHero storyboard (portal JWT)

```php
$client = Client::fromLogin('dev@partner.com', 'secret');

// Local file paths are auto-uploaded to hosted URLs.
$briefs = $client->storyboard->briefSuggestions('product.jpg', 'model.jpg', [
    'duration_seconds' => 30, 'language_code' => 'it',
]);
$candidates = $client->storyboard->candidates($briefs[0], 30, 'product.jpg', 'model.jpg');

$job = $client->storyboard->generate($briefs[0], 30, $candidates[0], 'product.jpg', 'model.jpg');
$sb  = $client->storyboard->wait($job['job_id']);          // polls until done/failed

$vjob  = $client->longvideo->start('product.jpg', null, $sb['storyboard']);
$final = $client->longvideo->render($vjob['job_id']);   // clips + final cut (portal JWT)
echo $final['final_video_url'];

// Re-render one segment (and the following ones) with a note.
$client->longvideo->regenerateSegment($vjob['job_id'], 1, 'slower camera', true);
```

## Agency act-as

```php
$client = Client::withApiKey('lz_live_AGENCY_KEY', null, 12345);   // managed user id
$client->images->generate('https://example.com/product.jpg', null, ['avatar_id' => 7]);
$client->withManagedUser(67890);   // switch brand; null clears it
```

The SDK adds `X-Managed-User-Id` to authenticated requests.

## Downloads

Download methods return the file **bytes** as a string, or write `$dest`
(creating parent directories) and return its path.

```php
$bytes = $client->images->download($logId);                    // string of bytes
$path  = $client->videos->download($logId, 'out/spot.mp4');    // saved to disk
$zip   = $client->bulk->downloadGenerated($bulkId, 'out/bulk.zip');
```

## Errors

```php
use HeroShots\Errors\BillingException;
use HeroShots\Errors\RateLimitException;
use HeroShots\Errors\ValidationException;

try {
    $client->videos->generate('https://example.com/p.jpg', ['duration_seconds' => 15]);
} catch (BillingException $e) {        // no payment method / billing details (VAT) missing
    echo $e->errorCode . ': ' . $e->getMessage();
} catch (RateLimitException $e) {      // already retried $maxRetries times
    echo 'retry after ' . $e->retryAfter;
}
```

API errors extend `HeroShots\Errors\ApiException` (`->errorCode`, `->status`,
`->details`), which extends `HeroShots\Errors\HeroShotsException` (also thrown
for client-side misuse, e.g. a local file with an API key).

HTTP 429 is retried automatically (`maxRetries: 2`, honours `Retry-After` up to
60 s, otherwise 1 s, 2 s, 4 s... up to 30 s). Multipart uploads are never
retried.

## Test

```bash
composer install
composer test       # PHPUnit, offline (Guzzle MockHandler)
```

## Billing

Generations cost credits. Check them with `$client->balance()` (`hs_credits` +
`wallet_balance`) and price a call first with `$client->pricing->quote()`. With
a portal JWT, `$client->stats()` returns the full raw `/api/auth/stats` payload.
See `examples/full_storyboard_flow.php`.
