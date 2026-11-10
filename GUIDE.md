# HeroShots SDK — Guida per casi d'uso

Guida operativa all'SDK PHP (`heroshots/sdk`), organizzata per **caso d'uso**. Ogni
sezione ha codice commentato passo-passo e un **esempio di risultato** (l'output
che ti aspetti). Per le firme complete dei metodi vedi il [README](./README.md).

L'SDK copre queste capacità:

| Caso d'uso | Risorsa |
|---|---|
| Account, salute del servizio | `account`, `$client->me()` |
| Foto (immagine prodotto, anche still-life), sfondi AI, diritti d'uso | `images` |
| Video corto | `videos` |
| Video lungo (multi-segmento) | `longvideo` |
| Still-life video | `stilllife` |
| Studio Hero (storyboard, tutte le fasi) | `storyboard` + `longvideo` |
| Avatar: lista, generazione, modifica, outfit, voce | `avatars` |
| Generazione massiva di immagini | `bulk` |
| Storico generazioni | `generations` |
| Listino e preventivi | `pricing` |
| Libreria: prodotti, collezioni, sfondi, categorie | `assets`, `collections`, `backgrounds`, `categories` |
| Documenti BYO (manleva, liberatoria) | `legal` |
| Agenzie e brand gestiti | `agency`, `brand` |
| Consultare il balance / stats complete | `$client->balance()`, `$client->stats()` |
| Caricare file → URL ospitato | `uploads` |

---

## Indice
- [0. Setup e concetti](#0-setup-e-concetti)
- [0.1 Agenzie: selezionare il brand/utente gestito](#01-agenzie-selezionare-il-brandutente-gestito)
- [1. Balance](#1-balance)
- [2. Foto](#2-foto)
- [3. Video corto](#3-video-corto)
- [4. Still-life video](#4-still-life-video)
- [5. Video lungo](#5-video-lungo)
- [6. Studio Hero — tutte le fasi](#6-studio-hero--tutte-le-fasi)
- [7. Avatar](#7-avatar)
- [8. Download](#8-download)
- [9. Preventivi, listino e diritti d'uso](#9-preventivi-listino-e-diritti-duso)
- [10. Storico generazioni](#10-storico-generazioni)
- [11. Bulk (generazione massiva)](#11-bulk-generazione-massiva)
- [12. Libreria](#12-libreria)
- [13. Documenti BYO (legal)](#13-documenti-byo-legal)
- [14. Agenzia e brand](#14-agenzia-e-brand)
- [Gestione errori](#gestione-errori)

---

## 0. Setup e concetti

```php
require 'vendor/autoload.php';
use HeroShots\Client;

// API key B2B (server-to-server). Viene letta anche da HEROSHOTS_API_KEY.
$client = Client::withApiKey('lz_live_...');

// Oppure login con email/password: salva il JWT e fa auto re-login alla scadenza (7 giorni).
// Il base URL è https://app.heroshots.ai (default, non serve passarlo).
$client = Client::fromLogin('dev@partner.com', 'secret');
```

**Quale credenziale serve dove:**

| Endpoint | API key | JWT portale |
|---|---|---|
| `/api/v1/*`: immagini, video, video lungo, still-life, avatar, bulk, libreria, storico, listino, agenzia, brand | ✅ | ✅ |
| Storyboard (Studio Hero), upload, candidati immagine, cambio sfondo still-life, documenti BYO | ❌ | ✅ |

- Le API key sono **rifiutate sul piano Free** (`ForbiddenException`, codice
  `plan_not_allowed`), tranne per gli account agenzia.
- `$client->usesApiKey()` dice quale credenziale sta usando il client.

Concetti validi per tutto l'SDK:

1. **Upload automatico (solo JWT portale).** Ovunque si passi un'immagine puoi
   dare un **path locale**: l'SDK lo carica e usa l'URL ospitato. Gli URL
   `http(s)` e i `data:` URI passano invariati. Con una API key passa URL
   pubblici `https://`: con un path locale l'SDK lancia `HeroShotsException`
   prima di inviare la richiesta.
2. **Job asincroni.** Le generazioni ritornano un `job_id`/`task_id`; chiama
   `wait(...)` per attendere il completamento (gestisce gli stati terminali).
3. **Costo.** Ogni generazione consuma crediti: controlla con `$client->balance()`
   e chiedi il prezzo prima con `$client->pricing->quote(...)`.
4. **Retry automatico.** Le risposte HTTP 429 vengono ripetute fino a
   `maxRetries` volte (default 2, rispettando `Retry-After`). Gli upload
   multipart non vengono mai ripetuti.
5. **File.** Dove serve un file (upload, bulk, asset, cover, documenti) puoi
   passare un path, una stringa di byte o una risorsa stream aperta.

---

## 0.1 Agenzie: selezionare il brand/utente gestito

Se usi una **API key agenzia**, prima scegli il brand/utente gestito su cui vuoi
operare. Il valore è il `managed_user_id` restituito dalla creazione/lista dei
managed client. Dopo averlo impostato, usi gli stessi metodi di generazione delle
sezioni successive: l'SDK aggiunge `X-Managed-User-Id` alle richieste autenticate.

```php
require 'vendor/autoload.php';
use HeroShots\Client;

$client = Client::withApiKey('lz_live_YOUR_AGENCY_KEY', null, 12345); // brand/utente gestito selezionato

// Da qui in poi le generazioni vengono eseguite come quel brand.
// Con una API key passa URL pubblici (niente path locali).
$gen = $client->images->generate('https://example.com/product.jpg', payload: [
    'still_life' => true,
    'ratio'      => '1:1',
]);

// Per passare a un altro brand gestito senza ricreare il client:
$client->withManagedUser(67890);
$v = $client->videos->generate('https://example.com/product.jpg', [
    'custom_prompt' => 'slow dolly-in',
]);

// Per tornare a chiamate senza act-as:
$client->withManagedUser(null);
```

La key agenzia deve avere `agency:act_as` e gli scope richiesti dalla route di
generazione. Lo stato della relazione e gli scope vengono validati dall'API.

---

## 1. Balance

Consulta i crediti residui prima di generare.

```php
$bal = $client->balance();

echo "HS credits: {$bal['hs_credits']}\n";
echo "Wallet: {$bal['wallet_balance']} €\n";

// Con JWT portale: risposta completa di GET /api/auth/stats (recent_generations, piani, flag).
$stats = $client->stats();
```

Con una **API key** `balance()` legge `GET /api/v1/me` e ritorna `hs_credits`,
`currency_credit` (wallet in EUR, anche come `wallet_balance`) e `billing_type`.
Con un **JWT portale** legge `/api/auth/stats` e aggiunge piano e contatori.

**Esempio di risultato (API key):**
```json
{ "hs_credits": 1240, "currency_credit": 48.5, "wallet_balance": 48.5, "billing_type": "debit" }
```

**Esempio di risultato (JWT portale):**
```json
{
  "hs_credits": 1240,
  "wallet_balance": 48.5,
  "total_credits_used": 360,
  "plan": { "wallet_balance": 48.5, "template_id": "scale", "output_resolution": "4K" }
}
```

---

## 2. Foto

Genera un'immagine prodotto. Per uno scatto **still-life** (solo prodotto, senza
soggetto) passa `still_life => true` e ometti il soggetto.

```php
// Con soggetto (path locali caricati in automatico).
$gen = $client->images->generate(
    productImageUrl: 'product.jpg',
    subjectImageUrl: 'model.jpg',
    payload: ['ratio' => '4:5']
);

// Oppure still-life (solo prodotto):
$gen = $client->images->generate('product.jpg', payload: ['still_life' => true, 'ratio' => '1:1']);

// Attende il completamento e legge l'immagine.
$done = $client->images->wait($gen['generation_id']);
echo $done['images'][0]['url'] ?? '';
```

Altre funzioni sulle immagini:

```php
// Sfondo generato dall'AI (size: 2K | 4K).
$bg = $client->images->generateBackground('marble studio, soft daylight', '4:5', '2K');

// Prompt di modifica per un'area ritagliata dell'immagine.
$edit = $client->images->analyzeEditRegion('data:image/png;base64,...', 'togli il riflesso');
```

**Esempio di risultato (`$done`):**
```json
{
  "generation_id": "img_9f2a",
  "status": "succeeded",
  "images": [{ "url": "https://cdn.heroshots.ai/out/img_9f2a_0.png", "width": 2048, "height": 2560 }],
  "_meta": { "credits_remaining": { "hs_credits": 1232 } }
}
```

---

## 3. Video corto

Animazione breve a partire da un'immagine.

```php
$v = $client->videos->generate('product.jpg', [
    'custom_prompt' => 'slow dolly-in, soft studio light',
    'ratio'         => '16:9',
    'resolution'    => '1080p',
]);

$done = $client->videos->wait($v['task_id']);   // attende succeeded/failed
echo $done['video_url'];

// Con API key: URL pubblici e avatar della libreria.
$v = $client->videos->generate(null, [
    'product_image_url' => 'https://example.com/product.jpg',
    'avatar_id'         => 42,
    'duration_seconds'  => 15,
]);
// Stato singolo (provider opzionale):
$st = $client->videos->status($v['task_id'] ?? $v['video_id']);
```

**Esempio di risultato (`$done`):**
```json
{ "task_id": "vid_5c1e", "status": "succeeded", "video_url": "https://cdn.heroshots.ai/out/vid_5c1e.mp4" }
```

---

## 4. Still-life video

Video di prodotto su sfondo, con eventuale cambio sfondo.

```php
$task = $client->stilllife->start(
    productUrl:      'product.jpg',
    backgroundUrl:   'background.jpg',
    customPrompt:    'rotating pedestal, luxury feel',
    durationSeconds: 5
);

$done = $client->stilllife->wait($task['task_id']);
echo $done['video_url'] ?? '';

// Opzionali: immagine di dettaglio e video di riferimento.
// $client->stilllife->start('https://.../p.jpg', 'https://.../bg.jpg',
//     detailImageUrl: 'https://.../detail.jpg', referenceVideoUrl: 'https://.../ref.mp4');

// (Opzionale, solo JWT portale) prova e applica un nuovo sfondo:
$client->stilllife->changeBackground($task['task_id'], ['background_url' => 'bg2.jpg']);
```

**Esempio di risultato (`$done`):**
```json
{ "task_id": "sl_77b0", "status": "succeeded", "video_url": "https://cdn.heroshots.ai/out/sl_77b0.mp4" }
```

---

## 5. Video lungo

Pipeline multi-segmento. Puoi avviarla da una semplice immagine + prompt, oppure
da uno storyboard di Studio Hero (vedi §6).

```php
$job = $client->longvideo->start(
    imageUrl:        'product.jpg',
    customPrompt:    '30s ad, three beats: reveal, detail, logo',
    ratio:           '16:9',
    resolution:      '1080p',
    durationSeconds: 30            // 30, 45 o 60
);

$final = $client->longvideo->wait($job['job_id']);   // success = "done"
echo $final['final_video_url'];

// Rigenera un segmento con un commento; cascade = true rifà anche i successivi.
$client->longvideo->regenerateSegment($job['job_id'], 1, 'camera più lenta', true);
```

**Esempio di risultato (`$final`):**
```json
{
  "job_id": 812,
  "status": "done",
  "segment_count": 3,
  "completed_count": 3,
  "segments": [
    { "index": 0, "status": "done", "video_url": "https://cdn/.../seg0.mp4" },
    { "index": 1, "status": "done", "video_url": "https://cdn/.../seg1.mp4" },
    { "index": 2, "status": "done", "video_url": "https://cdn/.../seg2.mp4" }
  ],
  "final_video_url": "https://cdn.heroshots.ai/out/long_812.mp4"
}
```

---

## 6. Studio Hero — tutte le fasi

Il flusso creativo completo del web app: brief → candidati → storyboard →
reference frame 4K → (revisione) → render video lungo.

```php
// --- Fase 0: referenze (path locali → URL ospitati) ---
$product = $client->uploads->image('product.jpg', 'products')['public_url'];
$subject = $client->uploads->image('model.jpg', 'subjects')['public_url'];
$duration = 30;

// --- Fase 1: brief suggeriti dall'AI a partire dalle immagini ---
$briefs = $client->storyboard->briefSuggestions($product, $subject, [
    'duration_seconds' => $duration,
    'language_code'    => 'it',
]);
$brief = $briefs[0];                       // es. l'utente sceglie il primo

// --- Fase 2: concept candidati → se ne sceglie uno ---
$candidates = $client->storyboard->candidates($brief, $duration, $product, $subject, false, [
    'ratio' => '16:9', 'language_code' => 'it',
]);
$selected = $candidates[0];

// --- Fase 3: storyboard completo (async, frame differiti) ---
$job = $client->storyboard->generate($brief, $duration, $selected, $product, $subject);
$sb  = $client->storyboard->wait($job['job_id']);          // status = "done"
$storyboard = $sb['storyboard'] ?? $sb['result'];

// --- Fase 4: reference frame 4K (async, con progresso) ---
$fjob = $client->storyboard->frames($storyboard, $product, $subject);
$frames = $client->storyboard->wait($fjob['job_id'], 3.0, 300.0, function (array $d) {
    if (isset($d['frames_partial'])) {
        echo "  frame pronti: " . count($d['frames_partial']) . "\n";
    }
});
$storyboard = $frames['storyboard'] ?? $storyboard;

// --- Fase 5 (opzionale): revisione ---
// $rev = $client->storyboard->revise('Rendi il segmento 2 più lento', $brief, $duration, $storyboard);
// $storyboard = $rev['storyboard'] ?? $storyboard;

// --- Fase 6: render video lungo dallo storyboard ---
$vjob  = $client->longvideo->start($product, null, $storyboard, '16:9');
$final = $client->longvideo->wait($vjob['job_id']);
echo $final['final_video_url'];
```

**Esempio di risultato per fase:**

Fase 1 — `briefSuggestions` →
```json
["Spot dinamico urban per il lancio sneaker",
 "Mood luxury slow-motion con focus sul materiale",
 "Storytelling lifestyle: dalla città al primo piano prodotto"]
```

Fase 2 — `candidates` (un elemento) →
```json
{
  "title": "Urban Drop",
  "angle": "energia di strada, taglio rapido",
  "clips": [{ "beat": "reveal", "action": "..." }, { "beat": "detail", "action": "..." }],
  "speaker": "voiceover maschile, tono hype",
  "payoff": "logo + claim finale"
}
```

Fase 3 — `wait($job['job_id'])` →
```json
{
  "job_id": "sb_4d10", "status": "done",
  "storyboard": {
    "segments": [
      { "index": 0, "visual_prompt": "...", "frame_prompt": "...", "image_url": null },
      { "index": 1, "visual_prompt": "...", "frame_prompt": "...", "image_url": null }
    ],
    "reference_frames_pending": true
  }
}
```

Fase 4 — `wait($fjob['job_id'])` (i frame diventano URL) →
```json
{
  "job_id": "fr_88a1", "status": "done",
  "storyboard": { "segments": [
    { "index": 0, "image_url": "https://cdn/.../seg0_start.png" },
    { "index": 1, "image_url": "https://cdn/.../seg1_start.png" }
  ] }
}
```

Fase 6 — `final` → vedi [§5](#5-video-lungo) (`final_video_url`).

> Vedi `examples/full_storyboard_flow.php` per lo script completo eseguibile.

---

## 7. Avatar

Elenca i tuoi avatar e quelli di default, generane di nuovi e modificali.

```php
// Lista (tuoi + default), con filtri opzionali.
$list = $client->avatars->list(['gender' => 'female', 'per_page' => 20]);
foreach ($list['avatars'] as $a) {
    echo "{$a['id']}  {$a['name']}\n";
}

// Dettaglio singolo.
$one = $client->avatars->get(42);

// Generazione con AI.
$gen = $client->avatars->generate([
    'generate_with_ai' => true,
    'prompt' => 'donna 30 anni, capelli scuri, sguardo deciso',
    'gender' => 'female',
], 'Aria');

// Da una foto per vista (viste ammesse: Avatars::VIEW_TYPES).
$client->avatars->createFromSteps('Luca', [
    'closeup'  => 'https://.../closeup.jpg',
    'fullbody' => 'https://.../fullbody.jpg',
]);

// Modifica.
$client->avatars->update(42, ['name' => 'Giulia']);
$client->avatars->uploadView(42, 'profile', 'https://.../profile.jpg');
$client->avatars->regenerateView(42, 'closeup');
$client->avatars->setImageSet(42, 'generated');      // original | generated
$client->avatars->generateVoice(42);

// Outfit: risolvi un link shop, vesti l'avatar, annulla.
$items = $client->avatars->resolveOutfitLink('https://shop.example.com/jacket');
$client->avatars->changeOutfit(42, [['slot' => 'top', 'url' => 'https://.../top.jpg']]);
$client->avatars->restoreOutfit(42);

// Documento di consenso firmato (DOCX).
$client->avatars->consentDocument(42, 'out/consent.docx');
```

**Esempio di risultato (`$list`):**
```json
{
  "avatars": [
    { "id": 42, "name": "Aria",  "image_url": "https://cdn/.../42.png" },
    { "id": 7,  "name": "Default Male 01", "image_url": "https://cdn/.../d7.png" }
  ],
  "total": 2, "page": 1, "pages": 1
}
```

---

## 8. Download

I metodi di download ritornano i **byte** del file come stringa, oppure, se
passi una destinazione, scrivono il file (creando le cartelle) e ritornano il path.

```php
$bytes = $client->images->download($logId);                   // stringa di byte
$path  = $client->videos->download($logId, 'out/spot.mp4');   // salvato su disco
```

---

## 9. Preventivi, listino e diritti d'uso

```php
$catalog = $client->pricing->catalog();                          // prezzi del tuo piano
$quote   = $client->pricing->quote('video', ['duration_seconds' => 15]);
echo $quote['total_eur'];

$tiers = $client->pricing->videoPackagesCatalog();
$pkg   = $client->pricing->videoPackagesQuote('growth');
$rules = $client->pricing->usageRightsRules();

// Diritti d'uso di un'immagine già generata: preventivo e upgrade.
$q = $client->images->usageRightsQuote($logId, null, 'paid_digital', '6_months');
$client->images->usageRightsUpgrade($logId, [['type' => 'paid_digital', 'duration' => '6_months']]);
```

---

## 10. Storico generazioni

```php
$page = $client->generations->list(['type' => 'video', 'per_page' => 20]);

// Tutte le pagine, una generazione alla volta.
foreach ($client->generations->iterate(['type' => 'image']) as $g) {
    echo $g['id'] . "\n";
}
```

---

## 11. Bulk (generazione massiva)

Una foto prodotto per item: crea la bulk, carica le immagini, avvia, attendi,
scarica lo zip.

```php
$bulk = $client->bulk->create('foto e-commerce su sfondo bianco', 42);
$client->bulk->uploadImages($bulk['id'], ['p1.jpg', 'p2.jpg', $bytes]);
$client->bulk->start($bulk['id']);

// Ritorna su completed, partially_completed o failed (non lancia eccezioni).
$final = $client->bulk->wait($bulk['id']);
echo $final['status'];

$client->bulk->downloadGenerated($bulk['id'], 'out/bulk.zip');
// Solo alcuni item:
$client->bulk->downloadGenerated($bulk['id'], 'out/some.zip', [101, 102]);
```

---

## 12. Libreria

```php
// Prodotti (multipart con la foto principale).
$asset = $client->assets->create('Borsa', 'bag.jpg', 4, 'pelle naturale');
$client->assets->addImage($asset['id'], 'bag_side.jpg');

// Collezioni di generazioni.
$col = $client->collections->create('Estate 2026');
$client->collections->addGeneration($col['id'], $logId);

// Sfondi salvati (da file o da URL).
$client->backgrounds->create(imageUrl: 'https://.../bg.jpg', name: 'Marmo');

// Categorie.
$all = $client->categories->list();
$client->categories->setSelected([1, 2]);
```

---

## 13. Documenti BYO (legal)

Solo JWT portale. Gli avatar di persone reali richiedono la manleva aziendale
(una volta per brand) e, per ogni persona, la liberatoria firmata più un
documento d'identità.

```php
$status = $client->legal->byoStatus();
$client->legal->manlevaTemplate('out/manleva.docx');
$client->legal->liberatoriaTemplate('out/liberatoria.docx');

$client->legal->uploadManleva($tenantId, 'manleva_firmata.pdf', 'Mario Rossi', 'CEO');

$release = $client->uploads->consentDocument('liberatoria_firmata.pdf')['public_url'];
$idDoc   = $client->uploads->consentDocument('documento.jpg')['public_url'];
$client->legal->attachLiberatoria($tenantId, $avatarId, $release, $idDoc, 'modella@example.com', '1995-04-12');
```

---

## 14. Agenzia e brand

```php
// Lato agenzia.
$clients = $client->agency->clients();
$client->agency->inviteClient('brand@example.com');
$client->agency->topup($relationshipId, 25.0);         // EUR dal wallet agenzia
$client->agency->updateClient($relationshipId, 'read_only');

// Lato brand.
$client->brand->acceptManagerInvite($token);
$rel = $client->brand->managerRelationship();
$client->brand->requestTermination('cambio fornitore');
```

Per generare come il brand vedi [§0.1](#01-agenzie-selezionare-il-brandutente-gestito).

---

## Gestione errori

Ogni errore dell'API lancia un'eccezione tipizzata che estende
`HeroShots\Errors\ApiException` (`->errorCode`, `->status`, `->details`), a sua
volta sottoclasse di `HeroShots\Errors\HeroShotsException` (lanciata anche per
errori lato client, es. un file locale con una API key).

```php
use HeroShots\Errors\BillingException;
use HeroShots\Errors\ValidationException;
use HeroShots\Errors\QuotaExceededException;

try {
    $client->videos->generate('https://example.com/product.jpg');
} catch (BillingException $e) {
    echo "Completa i dati di fatturazione nel portale: {$e->errorCode}";
} catch (QuotaExceededException $e) {
    echo "Crediti esauriti: " . $e->getMessage();
} catch (ValidationException $e) {
    echo "Payload non valido: {$e->errorCode}";
}
```

| Eccezione | Quando |
|---|---|
| `ValidationException` | payload non valido |
| `AuthException` / `TokenExpiredException` | token mancante/non valido/scaduto |
| `ForbiddenException` | piano/permessi non sufficienti |
| `NotFoundException` | risorsa o job inesistente/scaduto |
| `QuotaExceededException` | crediti/quota esauriti |
| `BillingException` | nessun metodo di pagamento o dati di fatturazione (P.IVA) mancanti |
| `ConflictException` | conflitto (es. email già registrata, cambio piano bloccato) |
| `ProviderException` | generazione fallita (anche job falliti) |
| `RateLimitException` | troppe richieste (429), dopo i retry automatici; `->retryAfter` in secondi |
| `ServiceUnavailableException` / `InternalException` | problemi lato server |
