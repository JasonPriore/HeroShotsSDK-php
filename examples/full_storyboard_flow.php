<?php

/**
 * End-to-end StudioHero flow via the HeroShots PHP SDK.
 * login -> upload -> (quote) -> brief suggestions -> candidates -> storyboard ->
 * reference frames -> long video. Every generation step is BILLABLE.
 *
 * Run:
 *   HEROSHOTS_BASE_URL=https://api.example.com \
 *   HEROSHOTS_EMAIL=dev@partner.com HEROSHOTS_PASSWORD=... \
 *   php examples/full_storyboard_flow.php product.jpg model.jpg
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use HeroShots\Client;

[$script, $productPath, $modelPath] = array_pad($argv, 3, null);
if (!$productPath || !$modelPath) {
    fwrite(STDERR, "usage: full_storyboard_flow.php <product.jpg> <model.jpg>\n");
    exit(1);
}

$client = Client::fromLogin(
    getenv('HEROSHOTS_BASE_URL'),
    getenv('HEROSHOTS_EMAIL'),
    getenv('HEROSHOTS_PASSWORD')
);
echo "logged in as " . $client->me()['email'] . "\n";

// 1. Upload references (local files -> hosted URLs).
$productUrl = $client->uploads->image($productPath, 'products')['public_url'];
$subjectUrl = $client->uploads->image($modelPath, 'subjects')['public_url'];

$duration = 30;
// 2. (Optional) check the balance before spending credits.
echo "balance: " . json_encode($client->balance()) . "\n";

// 3. Brief suggestions.
$briefs = $client->storyboard->briefSuggestions($productUrl, $subjectUrl, [
    'duration_seconds' => $duration,
    'language_code' => 'it',
]);
$brief = $briefs[0];
echo "brief: {$brief}\n";

// 4. Concept candidates -> pick one.
$candidates = $client->storyboard->candidates($brief, $duration, $productUrl, $subjectUrl, false, [
    'ratio' => '16:9',
    'language_code' => 'it',
]);
$selected = $candidates[0];

// 5. Full storyboard (async, frames deferred).
$job = $client->storyboard->generate($brief, $duration, $selected, $productUrl, $subjectUrl);
$sb = $client->storyboard->wait($job['job_id']);
$storyboard = $sb['storyboard'] ?? $sb['result'];
echo "storyboard ready: " . count($storyboard['segments'] ?? []) . " segments\n";

// 6. Reference frames (async, progressive).
$fjob = $client->storyboard->frames($storyboard, $productUrl, $subjectUrl);
$frames = $client->storyboard->wait($fjob['job_id'], 3.0, 300.0, function (array $d): void {
    if (isset($d['frames_partial'])) {
        echo "  frames_partial: " . json_encode($d['frames_partial']) . "\n";
    }
});
$storyboard = $frames['storyboard'] ?? $storyboard;

// 7. Long video render.
$vjob = $client->longvideo->start($productUrl, null, $storyboard, '16:9');
$result = $client->longvideo->wait($vjob['job_id']);
echo "FINAL VIDEO: " . ($result['final_video_url'] ?? '(none)') . "\n";
