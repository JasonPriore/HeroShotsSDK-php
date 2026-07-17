# HeroShots SDK — Guida per casi d'uso

Guida operativa all'SDK PHP (`heroshots/sdk`), organizzata per **caso d'uso**. Ogni
sezione ha codice commentato passo-passo e un **esempio di risultato** (l'output
che ti aspetti). Per le firme complete dei metodi vedi il [README](./README.md).

L'SDK copre esattamente queste capacità:

| Caso d'uso | Risorsa |
|---|---|
| Foto (immagine prodotto, anche still-life) | `images` |
| Video corto | `videos` |
| Video lungo (multi-segmento) | `longvideo` |
| Still-life video | `stilllife` |
| Studio Hero (storyboard, tutte le fasi) | `storyboard` + `longvideo` |
| Avatar: vedere i propri + default, generarne | `avatars` |
| Consultare il balance | `$client->balance()` |
| Consultare stats account complete | `$client->stats()` |
| Caricare file → URL ospitato (usato da tutto) | `uploads` |

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
- [Gestione errori](#gestione-errori)

---

## 0. Setup e concetti

```php
require 'vendor/autoload.php';
use HeroShots\Client;

// Login con email/password: salva il JWT e fa auto re-login alla scadenza (7 giorni).
// Il base URL è https://app.heroshots.ai (default, non serve passarlo).
$client = Client::fromLogin('dev@partner.com', 'secret');
```

Tre concetti validi per tutto l'SDK:

1. **Upload automatico.** Ovunque si passi un'immagine puoi dare un **path locale**:
   l'SDK lo carica e usa l'URL ospitato. Gli URL `http(s)` passano invariati.
2. **Job asincroni.** Le generazioni ritornano un `job_id`/`task_id`; chiama
   `wait(...)` per attendere il completamento (gestisce gli stati terminali).
3. **Costo.** Ogni generazione consuma crediti: controlla con `$client->balance()`.

---

## 0.1 Agenzie: selezionare il brand/utente gestito

Se usi una **API key agenzia**, prima scegli il brand/utente gestito su cui vuoi
operare. Il valore è il `managed_user_id` restituito dalla creazione/lista dei
managed client. Dopo averlo impostato, usi gli stessi metodi di generazione delle
sezioni successive: l'SDK aggiunge `X-Managed-User-Id` alle richieste autenticate.

```php
require 'vendor/autoload.php';
use HeroShots\Client;

$client = new Client(
    token: 'lz_live_YOUR_AGENCY_KEY',
    managedUserId: 12345 // brand/utente gestito selezionato
);

// Da qui in poi le generazioni vengono eseguite come quel brand.
$gen = $client->images->generate('product.jpg', payload: [
    'still_life' => true,
    'ratio'      => '1:1',
]);

// Per passare a un altro brand gestito senza ricreare il client:
$client->withManagedUser(67890);
$v = $client->videos->generate('product.jpg', [
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
$bal = $client->balance();   // vista compatta di GET /api/auth/stats

echo "HS credits: {$bal['hs_credits']}\n";
echo "Wallet: {$bal['wallet_balance']} €\n";

// Se vuoi la risposta completa dell'endpoint (recent_generations, piani, flag):
$stats = $client->stats();
```

**Esempio di risultato:**
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

// (Opzionale) prova e applica un nuovo sfondo:
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
    imageUrl:     'product.jpg',
    customPrompt: '15s ad, three beats: reveal, detail, logo',
    ratio:        '16:9',
    resolution:   '1080p',
    payload:      ['duration_seconds' => 15]
);

$final = $client->longvideo->wait($job['job_id']);   // success = "done"
echo $final['final_video_url'];
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

Elenca i tuoi avatar e quelli di default, e richiedi la generazione di uno nuovo.

```php
// Lista (tuoi + default).
$list = $client->avatars->list();
foreach ($list['avatars'] as $a) {
    echo "{$a['id']}  {$a['name']}  ({$a['kind']})\n";
}

// Dettaglio singolo.
$one = $client->avatars->get(42);

// Richiesta generazione nuovo avatar.
$gen = $client->avatars->generate([
    'prompt' => 'donna 30 anni, capelli scuri, sguardo deciso',
    'gender' => 'female',
]);
```

**Esempio di risultato (`$list`):**
```json
{
  "avatars": [
    { "id": 42, "name": "Aria",  "kind": "owned",   "image_url": "https://cdn/.../42.png" },
    { "id": 7,  "name": "Default Male 01", "kind": "default", "image_url": "https://cdn/.../d7.png" }
  ]
}
```

---

## Gestione errori

Ogni errore lancia un'eccezione tipizzata che estende `HeroShots\Errors\ApiException`
(`->errorCode`, `->status`, `->details`).

```php
use HeroShots\Errors\ValidationException;
use HeroShots\Errors\QuotaExceededException;

try {
    $client->videos->generate('product.jpg');
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
| `ProviderException` | generazione fallita (anche job falliti) |
| `RateLimitException` | troppe richieste (429) |
| `ServiceUnavailableException` / `InternalException` | problemi lato server |
