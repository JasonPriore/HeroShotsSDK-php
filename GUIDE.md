# HeroShots SDK — Guida completa

Guida operativa all'SDK PHP (`heroshots/sdk`). Documenta **tutto** ciò che il
client può fare. È pensata anche come checklist di revisione: ogni gruppo di
metodi ha un flag **B2B** per aiutarti a decidere cosa esporre ai partner esterni
e cosa rimuovere. In fondo c'è la sezione [Cosa rimuovere per il B2B](#cosa-rimuovere-per-il-b2b)
con la procedura esatta.

> Legenda flag B2B (è solo un suggerimento, decidi tu):
> - 🟢 **Tieni** — superficie tech tipica di un partner che integra.
> - 🟡 **Valuta** — utile ma sensibile; esporlo solo se serve davvero.
> - 🔴 **Rimuovi** — funzioni di account/portale/billing personali: un partner B2B non dovrebbe averle.

---

## Indice

1. [Modello di autenticazione](#1-modello-di-autenticazione)
2. [Installazione](#2-installazione)
3. [Concetti trasversali](#3-concetti-trasversali)
   - Envelope di risposta · Errori · Polling dei job · Upload automatico · Billing
4. [Risorse e metodi (tutto)](#4-risorse-e-metodi)
5. [Flussi completi end-to-end](#5-flussi-completi)
6. [Cosa rimuovere per il B2B](#cosa-rimuovere-per-il-b2b)

---

## 1. Modello di autenticazione

L'SDK usa un **JWT del portale** (`Authorization: Bearer <jwt>`), ottenuto da
`POST /api/auth/login`. Caratteristiche:

- Algoritmo **HS256**, validità **7 giorni**, **nessun endpoint di refresh**.
- Il client fa **auto re-login una volta** su `token_expired`, se costruito con
  email+password.
- È **diverso** dalla API key B2B `lz_live_`: gli endpoint creativi (storyboard,
  long-video, still-life, audio, upload) accettano **solo** il JWT del portale e
  rifiutano la API key. Per questo l'integrazione si autentica con email+password
  di un utente dedicato.

> ⚠️ **Account "model":** un JWT di tipo model è bloccato su tutti gli endpoint
> `/api/v1/*` (images.generate, videos.generate, avatars, library, bulk,
> dreamcast). Per copertura totale usa un account brand/standard.

```php
use HeroShots\Client;

// Consigliato: login con credenziali (salva il JWT, auto re-login alla scadenza).
$client = Client::fromLogin('https://api.example.com', 'dev@partner.com', 'secret');

// Oppure riusa un token già emesso (HEROSHOTS_BASE_URL come env supportata).
$client = new Client('https://api.example.com', 'eyJ...');
```

---

## 2. Installazione

```bash
composer require heroshots/sdk
```

Requisiti: PHP 8.1+, dipendenza runtime `guzzlehttp/guzzle`. Namespace PSR-4
`HeroShots\`.

---

## 3. Concetti trasversali

### Envelope di risposta
Il backend ha due stili, gestiti dall'SDK in automatico:
- `/api/v1/*` → `{ "success", "data", "meta" }` → l'SDK restituisce `data`; i
  metadati di billing/crediti sono sotto `$result['_meta']`.
- Tutto il resto (portale, `/api/auth/*`, root) → JSON "nudo", restituito così com'è.

### Errori
Ogni errore lancia un'eccezione tipizzata che estende `HeroShots\Errors\ApiException`,
con `->errorCode`, `->status`, `->details`.

| Eccezione | Quando |
|---|---|
| `ValidationException` | payload non valido (`validation_error`) |
| `AuthException` | token mancante/non valido, utente sconosciuto |
| `TokenExpiredException` | JWT scaduto (→ re-login) |
| `ForbiddenException` | piano/scope/tenant non permessi |
| `NotFoundException` | risorsa inesistente o job scaduto |
| `ConflictException` | conflitto (es. email già registrata) |
| `QuotaExceededException` | crediti/quota esauriti |
| `PayloadTooLargeException` | upload troppo grande |
| `ProviderException` | provider di generazione fallito (anche job falliti) |
| `ServiceUnavailableException` | dipendenza non configurata |
| `RateLimitException` | HTTP 429 |
| `InternalException` | 5xx |

```php
use HeroShots\Errors\ValidationException;

try {
    $client->storyboard->candidates('', 30, 'p.jpg');
} catch (ValidationException $e) {
    echo $e->errorCode . ': ' . $e->getMessage();
}
```

### Polling dei job
Le generazioni async espongono `status()` + `wait()`. Gli stati terminali
differiscono per superficie e sono gestiti per te:
- storyboard e long-video: success = `done`, fail = `failed`
- video/image singolo: success = `succeeded`, fail = `failed`

```php
$job = $client->storyboard->generate(...);
$done = $client->storyboard->wait($job['job_id'], 3.0, 300.0, function (array $d) {
    // callback di progresso opzionale (es. frames_partial)
});
```

### Upload automatico di file locali
Ovunque un metodo accetti un URL immagine, puoi passare un **path locale**:
l'SDK lo carica su `/upload/image` e usa l'`public_url` ottenuto. Gli URL http(s)
passano invariati.

### Billing
**Ogni generazione costa crediti.** Usa `pricing->quote(...)` per stimare il costo
prima di lanciare.

---

## 4. Risorse e metodi

Tutte le risorse sono accessibili da `$client-><nome>`. Per i campi extra del
backend usa l'array `$payload`/`$fields` finale: viene inoltrato tale e quale.

### 4.1 `uploads` — 🟢 Tieni
Carica file e ottieni URL ospitati (necessari alle generazioni).

| Metodo | Endpoint | Note |
|---|---|---|
| `uploads->image($path, $folder=null)` | POST `/upload/image` | → `{public_url, filename, folder}`. `folder` ∈ subjects, products, avatars, references, background, body_posture, reference-videos, avatar-consents |
| `uploads->referenceAudio($path)` | POST `/upload/reference-audio` | audio di riferimento per voice clone |

### 4.2 `storyboard` — 🟢 Tieni (cuore creativo StudioHero)
Brief → candidati → storyboard (async) → reference frame (async) → revisione.

| Metodo | Endpoint |
|---|---|
| `storyboard->briefSuggestions($productImageUrl=null, $subjectImageUrl=null, $payload=[])` | POST `/video/storyboard/brief-suggestions` → string[] |
| `storyboard->candidates($customPrompt, $durationSeconds, $productImageUrl=null, $subjectImageUrl=null, $async=false, $payload=[])` | POST `/video/storyboard/candidates` |
| `storyboard->generate($customPrompt, $durationSeconds, $selectedCandidate=null, $productImageUrl=null, $subjectImageUrl=null, $async=true, $deferReferenceFrames=true, $payload=[])` | POST `/video/storyboard` |
| `storyboard->frames($storyboard, $productImageUrl=null, $subjectImageUrl=null, $async=true, $payload=[])` | POST `/video/storyboard/frames` |
| `storyboard->revise($revisionRequest, $customPrompt, $durationSeconds, $storyboard, $payload=[])` | POST `/video/storyboard/revise` |
| `storyboard->status($jobId)` / `wait(...)` | GET `/video/storyboard/status/<job_id>` |
| `storyboard->subjectMeasurements($payload=[])` | POST `/subject/estimate-measurements` |
| `storyboard->analyzeProduct($payload=[])` | POST `/product/analyze` |
| `storyboard->creativity($payload=[])` | POST `/creativity/generate` |

Durate valide: 6, 8, 12, 15, 30, 45, 60.

### 4.3 `longvideo` — 🟢 Tieni
Pipeline video multi-segmento (usa lo storyboard).

| Metodo | Endpoint |
|---|---|
| `longvideo->start($imageUrl=null, $customPrompt=null, $storyboard=null, $ratio='16:9', $resolution='1080p', $payload=[])` | POST `/api/auth/long-video/start` |
| `longvideo->status($jobId)` / `wait(...)` | GET `/api/auth/long-video/status/<job_id>` → `final_video_url` |
| `longvideo->view($token)` | GET `/api/auth/long-video/view/<token>` |

### 4.4 `stilllife` — 🟢 Tieni
Video still-life di prodotto.

| Metodo | Endpoint |
|---|---|
| `stilllife->start($productUrl, $backgroundUrl, $customPrompt=null, $ratio='16:9', $resolution='1080p', $durationSeconds=5, $payload=[])` | POST `/api/auth/stilllife-video/start` |
| `stilllife->status($taskId)` / `wait(...)` | GET `/api/auth/stilllife-video/status/<task_id>` |
| `stilllife->previewBackground($taskId, $payload=[])` | POST `.../preview-background` |
| `stilllife->changeBackground($taskId, $payload=[])` | POST `.../change-background` |
| `stilllife->changeBackgroundStatus($taskId)` | GET `.../change-background/status` |

### 4.5 `images` — 🟢 Tieni
Generazione immagini (B2B + flusso candidati del portale).

| Metodo | Endpoint | Auth |
|---|---|---|
| `images->generate($productImageUrl, $subjectImageUrl=null, $payload=[])` | POST `/api/v1/image/generate` | JWT o API key |
| `images->candidates($productImageUrl, $subjectImageUrl=null, $payload=[])` | POST `/api/auth/image/generate/candidates` | JWT |
| `images->selectCandidate($token, $payload=[])` | POST `/api/auth/image/generate/candidates/<token>/select` | JWT |
| `images->status($generationId)` / `wait(...)` | GET `/api/v1/image/status/<id>` | |
| `images->download($logId)` | GET `/api/v1/image/<log_id>/download` | |

### 4.6 `videos` — 🟢 Tieni
Video corto.

| Metodo | Endpoint |
|---|---|
| `videos->generate($imageUrl=null, $payload=[])` | POST `/api/v1/video/generate` |
| `videos->status($taskId)` / `wait(...)` | GET `/video/status/<task_id>` → `video_url` |

### 4.7 `avatars` — 🟡 Valuta
Gestione avatar (bloccato per account model).

`list, get, update, delete, generate, improvePrompt, regenerateView,
regenerateVariants, uploadView, createFromSteps, analyzeAttributes`
→ `/api/v1/avatar/*`

### 4.8 `library` — 🟡 Valuta
Asset/sfondi/collezioni/categorie del workspace (gated brand/admin).

- `library->backgrounds` → list/create/get/update/delete
- `library->assets` → list/get/create/update/delete/addImage/deleteImage
- `library->collections` → list/create/get/update/delete/generations/addGeneration/removeGeneration/moveGeneration/availableGenerations
- `library->categories` → list/selected/setSelected

### 4.9 `bulk` — 🟡 Valuta
Job bulk di generazione → `/api/v1/bulk/*`
`list, create, get, update, delete, start, addImages, replaceProduct, deleteItem,
cancelItem, regenerateItem, regenerateAll, itemAttempts, selectAttempt, previews,
downloadItem, downloadGenerated`

### 4.10 `dreamcast` — 🟡 Valuta
Task video Dreamcast → `/api/v1/dreamcast/tasks`
`list, create, createFromUrl, get, delete`

### 4.11 `generations` — 🟡 Valuta
Storico generazioni e item "pinned" dell'utente.
`list, details, replicaData, previewImage, download, delete, view,
requestApproval, requestApprovalBulk, pinned, pin, unpin` → `/api/auth/generations*`

### 4.12 `pricing` — 🟢 quote / 🔴 acquisti
Quote e listini.

| Metodo | Flag |
|---|---|
| `pricing->catalog()` | 🟢 |
| `pricing->quote($callType, $payload=[])` | 🟢 (stima costo prima di generare) |
| `pricing->usageRightsRules()` | 🟢 |
| `pricing->usageRightsQuote($logId, $fields=[])` | 🟢 |
| `pricing->usageRightsUpgrade($logId, $fields=[])` | 🟡 (spende) |
| `pricing->videoPackagesCatalog()` | 🟡 |
| `pricing->videoPackagesQuote($fields)` | 🟡 |
| `pricing->purchaseVideoPackage($fields)` | 🔴 (acquisto) |
| `pricing->listVideoPackages()` | 🟡 |

### 4.13 `account` — 🔴 Rimuovi
Profilo, piano, statistiche personali dell'utente del portale.
`me, getProfile, updateProfile, stats, getPlan, setPlan, cancelPlan,
getDefaultUsageRights, setDefaultUsageRights, getWatermarkLogo, setWatermarkLogo`

### 4.14 `billing` — 🔴 Rimuovi
Metodi di pagamento, wallet, transazioni, Stripe. **Da non esporre a un partner.**
`paymentMethods, addPaymentMethod, deletePaymentMethod, getBillingInfo,
updateBillingInfo, topup, walletMovements, getAutoRecharge, setAutoRecharge,
disableAutoRecharge, transactions, invoice, stripeSetupIntent,
stripeConfirmPaymentMethod`

### 4.15 `audio` — 🟡 Valuta
Voice clone e TTS.
`voices, clone, delete, preview` → `/audio/*`

### 4.16 `model` — 🔴 Rimuovi
Portale per account "model" (talent). Non pertinente a un partner tech.
`updateVat, stats, getAvatar, updateAvatar, publishAvatar, approvals, approve, reject`

### 4.17 `connectors` — 🔴 Rimuovi
Connettori interni (es. chiave connector Claude).
`claude, createClaudeKey` → `/api/auth/connectors/claude*`

---

## 5. Flussi completi

### 5.1 Storyboard → video lungo (end-to-end)
```php
use HeroShots\Client;

$client = Client::fromLogin($base, $email, $password);

// 1. Upload referenze (path locali → URL ospitati automaticamente).
$product = $client->uploads->image('product.jpg', 'products')['public_url'];
$subject = $client->uploads->image('model.jpg', 'subjects')['public_url'];

// 2. (Opzionale) stima costo.
$quote = $client->pricing->quote('video', ['duration_seconds' => 30, 'ratio' => '16:9']);

// 3. Brief suggeriti.
$briefs = $client->storyboard->briefSuggestions($product, $subject, [
    'duration_seconds' => 30, 'language_code' => 'it',
]);

// 4. Candidati concept → scegline uno.
$cands = $client->storyboard->candidates($briefs[0], 30, $product, $subject);
$selected = $cands[0];

// 5. Storyboard (async, frame differiti).
$job = $client->storyboard->generate($briefs[0], 30, $selected, $product, $subject);
$sb  = $client->storyboard->wait($job['job_id']);
$storyboard = $sb['storyboard'] ?? $sb['result'];

// 6. Reference frame 4K (async, progressivo).
$fjob = $client->storyboard->frames($storyboard, $product, $subject);
$frames = $client->storyboard->wait($fjob['job_id']);
$storyboard = $frames['storyboard'] ?? $storyboard;

// 7. Render video lungo.
$vjob = $client->longvideo->start($product, null, $storyboard, '16:9');
$final = $client->longvideo->wait($vjob['job_id']);
echo $final['final_video_url'];
```
(Vedi anche `examples/full_storyboard_flow.php`.)

### 5.2 Immagine singola
```php
$gen = $client->images->generate('product.jpg', 'model.jpg', ['ratio' => '4:5']);
$done = $client->images->wait($gen['generation_id']);
```

### 5.3 Video corto
```php
$v = $client->videos->generate('product.jpg', ['custom_prompt' => '...']);
$done = $client->videos->wait($v['task_id']);
echo $done['video_url'];
```

### 5.4 Still-life
```php
$t = $client->stilllife->start('product.jpg', 'background.jpg', durationSeconds: 5);
$done = $client->stilllife->wait($t['task_id']);
```

---

## Cosa rimuovere per il B2B

Suggerimento di superficie "minima tech" da esporre a un partner:

**Tieni (🟢):** `uploads`, `storyboard`, `longvideo`, `stilllife`, `images`,
`videos`, `pricing->quote/catalog/usageRights*`.

**Da rimuovere (🔴) tipicamente:** `account`, `billing`, `model`, `connectors`,
e gli acquisti in `pricing` (`purchaseVideoPackage`).

**Da valutare (🟡):** `avatars`, `library`, `bulk`, `dreamcast`, `generations`,
`audio`, pacchetti video in `pricing`.

### Come rimuovere una risorsa (procedura)
Per togliere, es., `billing`:
1. Cancella il file `src/Resources/Billing.php`.
2. In `src/Client.php` rimuovi: la proprietà `public Billing $billing;`, la riga
   `use HeroShots\Resources\Billing;` e l'istanziazione `$this->billing = new Billing($this);`.
3. (Se presente) togli i relativi test in `tests/`.
4. `composer test` per confermare che tutto compila.
5. Bump versione (es. `v0.2.0`), tag e push.

Per togliere **singoli metodi** (es. solo `purchaseVideoPackage` da `pricing`),
cancella il metodo dalla classe `Pricing` e lascia il resto.

> Quando mi dici quali blocchi tagliare, applico le rimozioni su **entrambi** gli
> SDK (PHP qui + Python in `lazarus/sdk/python`) per mantenerli allineati, aggiorno
> README/guida e il `parity_check.py`.
