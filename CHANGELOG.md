# Changelog

## 0.3.1

### Fixed
- Long video: `wait()` now returns when the clips are ready (`segments_ready`).
  Like the web app, a job pauses there until the clips are exported, so 0.3.0
  waited until the timeout. Each `segments[].video_url` is set at that point.
- An expired portal login reported as `invalid_or_expired_token` now triggers
  the automatic re-login (it maps to the token-expired error).
- Guides: image results (`url` / `output_url`), video id (`video_id`), still-life
  result (`processed_video_url`), `aspect_ratio` values, outfit items and the
  id to pass to `download()` now match the API.

### Added
- Long video `export()` (joins the clips into `final_video_url`, not billed) and
  `render()` (wait + export + wait for the final video). Portal login only.

## 0.3.0

Aligns the PHP SDK 1:1 with the Python SDK and covers the whole public `/api/v1`
surface.

### Breaking

- **Long video and still-life video moved to `/api/v1`.** `LongVideo::start()` /
  `status()` / `wait()` now call `/api/v1/long-video/start|status` and
  `StillLife::start()` / `status()` / `wait()` call
  `/api/v1/stilllife-video/start|status`, because the old
  `/api/auth/long-video/start|status` and `/api/auth/stilllife-video/start|status`
  routes were removed from the backend on 2026-07-16. Both now work with an API
  key as well as a portal JWT. `LongVideo::view()` and the still-life background
  edits stay on `/api/auth` (portal JWT).
- `Videos::status()` (and therefore `Videos::wait()`) now polls
  `GET /api/v1/video/status/{id}` instead of the portal `/video/status/{id}`.
- **Downloads return bytes.** `Images::download()` returns the file contents as a
  string (it used to decode JSON), or writes `$dest` and returns its path.
- With an API key, passing a **local file path** where an image URL is expected
  throws `HeroShotsException` (uploads need a portal JWT) instead of attempting
  the upload. Pass public `https://` URLs or `data:` URIs.
- `ApiException` now extends the new `HeroShotsException` base (still a
  `RuntimeException`), so existing `catch (ApiException ...)` blocks keep working.

### Added

- API-key authentication: new last constructor argument `?string $apiKey`
  (alias of `$token`, also read from `HEROSHOTS_API_KEY`), `Client::withApiKey()`
  and `Client::usesApiKey()`.
- `Client::me()` uses `/api/v1/me` with an API key; `Client::balance()` with an
  API key reads `/api/v1/me` and returns `hs_credits`, `currency_credit`,
  `wallet_balance` and `billing_type`. JWT behaviour is unchanged.
- Automatic retry of HTTP 429 (`int $maxRetries = 2` constructor argument):
  honours `Retry-After` (capped at 60 s), otherwise backs off 1 s, 2 s, 4 s...
  (capped at 30 s). Multipart requests are never retried. `RateLimitException`
  gains `?float $retryAfter`. The delay function is the public `$sleeper`
  callable (overridable in tests).
- Every request sends `User-Agent: heroshots-php/0.3.0` (`Client::VERSION`) and
  `Accept: application/json` (not for raw downloads). Null query values are
  dropped; an empty JSON body is sent as `{}`.
- `Client::download()` and the `raw` request option for binary endpoints.
- `BillingException` (codes `no_payment_method`,
  `wallet_vat_validation_required`); `plan_change_locked` now maps to
  `ConflictException`.
- `Images`: `generateBackground()`, `usageRightsQuote()` (GET with a single
  type/duration, POST with a `usage_rights` list), `usageRightsUpgrade()`,
  `analyzeEditRegion()`; `generate()`/`candidates()` also resolve
  `background_image_url`, `body_posture_image_url`,
  `hairstyle_reference_image_url`, `text_detail_image_url`,
  `worn_scale_reference_url` and `additional_product_image_urls`.
- `Videos`: `download()`, `status()` optional `$provider`; `generate()` also
  resolves `product_image_url` / `subject_image_url` in the payload.
- `LongVideo`: `?int $durationSeconds` on `start()` and
  `regenerateSegment($jobId, $segmentIndex, $comment, $cascade)`.
- `StillLife::start()`: `$detailImageUrl` and `$referenceVideoUrl`.
- `Avatars`: `consentDocument`, `createFromSteps`, `improvePrompt`,
  `analyzeAttributes`, `update`, `delete`, `uploadView`, `regenerateView`,
  `regenerateVariants`, `generatedVersion`, `setImageSet`, `generateSheet`,
  `generateVoice`, `resolveOutfitLink`, `changeOutfit`, `restoreOutfit`,
  `VIEW_TYPES`; `generate()` takes an optional `$name` and resolves `image_url`.
- `Uploads`: `consentDocument()`, `referenceAudio()`; uploads accept a path, a
  string of bytes or a stream resource.
- New resources on the client: `account`, `bulk`, `generations` (with an
  `iterate()` generator), `pricing`, `assets`, `collections`, `backgrounds`,
  `categories`, `legal`, `agency`, `brand`.
- README and GUIDE describe the full surface and which credential each route
  accepts.

## 0.2.3

- Agency act-as via `managedUserId` / `withManagedUser()` (`X-Managed-User-Id`).

## 0.2.2

- `Client::stats()` returns the full raw `/api/auth/stats` payload; `balance()` is a compact view.

## 0.2.1

- Base URL defaults to `https://app.heroshots.ai`.
