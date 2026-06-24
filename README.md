# heroshots/sdk (PHP SDK)

High-level PHP client for the HeroShots / StudioHero platform, authenticated with
a **portal JWT**. Covers the full JWT-reachable API surface. PSR-4 namespace
`HeroShots\`.

## Install

```bash
composer require heroshots/sdk
```

Requires PHP 8.1+. Runtime dependency: `guzzlehttp/guzzle`.

## Authenticate

```php
use HeroShots\Client;

// Email/password login: stores the JWT, auto re-logins on expiry.
$client = Client::fromLogin('https://api.example.com', 'dev@partner.com', 'secret');

// Or reuse an existing token (HEROSHOTS_BASE_URL env supported).
$client = new Client('https://api.example.com', 'eyJ...');
```

## Usage

```php
// Local file paths are auto-uploaded to hosted URLs.
$briefs = $client->storyboard->briefSuggestions('product.jpg', 'model.jpg', [
    'duration_seconds' => 30, 'language_code' => 'it',
]);

$candidates = $client->storyboard->candidates($briefs[0], 30, 'product.jpg', 'model.jpg');

$job = $client->storyboard->generate($briefs[0], 30, $candidates[0], 'product.jpg', 'model.jpg');
$sb  = $client->storyboard->wait($job['job_id']);          // polls until done/failed

$vjob  = $client->longvideo->start('product.jpg', null, $sb['storyboard']);
$final = $client->longvideo->wait($vjob['job_id']);
echo $final['final_video_url'];
```

Extra backend fields go in the trailing `$payload`/`$fields` array argument and
are forwarded verbatim. `/api/v1` billing metadata is under `$result['_meta']`.

## Resources

`account` · `audio` · `avatars` · `billing` · `bulk` · `connectors` ·
`dreamcast` · `generations` · `images` · `library` · `longvideo` · `model` ·
`pricing` · `stilllife` · `storyboard` · `uploads` · `videos`

## Errors

```php
use HeroShots\Errors\ValidationException;

try {
    $client->storyboard->candidates('', 30, 'p.jpg');
} catch (ValidationException $e) {
    echo $e->errorCode . ': ' . $e->getMessage();
}
```

All errors extend `HeroShots\Errors\ApiException` (`->errorCode`, `->status`,
`->details`).

## Test

```bash
composer install
composer test       # PHPUnit, offline (Guzzle MockHandler)
```

## Billing

Generations cost credits. Preview with `$client->pricing->quote('video', ...)`
before running. See `examples/full_storyboard_flow.php`.
