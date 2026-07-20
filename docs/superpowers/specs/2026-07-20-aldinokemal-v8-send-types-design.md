# aldinokemal_v8: Send Message (Text/Image/File/Video) — Design

## Context

`WhatsappInterface` and its three drivers (`AldinokemalWhatsapp` "v1", `AldinokemalV8Whatsapp`, `WuzapiWhatsapp`) currently support two outbound message shapes: plain text (`message()`) and image (`image()`), both sent as a URL/storage-path string, never a real binary upload. `.aldinokemal_v8/openapi.yaml` (tag `send`, "Send Message (Text/Image/File/Video)") documents four richer endpoints for the v8 driver: `/send/message`, `/send/image`, `/send/file`, `/send/video`, each accepting a superset of optional fields (`reply_message_id`, `is_forwarded`, `duration`, and, for image/video, `view_once`/`compress`/`gif_playback`).

Goal: add `file()` and `video()` to the fluent builder, plus the optional flags, fully implemented for `aldinokemal_v8`, with `aldinokemal` (v1) and `wuzapi` satisfying the interface without guessing at undocumented endpoints.

## Decisions from brainstorming

- `file()`/`video()` follow the existing `image()` shape: a single `string` (URL or local storage path), not a real multipart binary upload. No signature change to `image()`.
- Optional fields supported: `is_forwarded`, `duration`, `view_once`, `compress`, `gif_playback`. `mentions` (text-only, poll-adjacent) is out of scope.
- Driver `aldinokemal` (v1): `file()`/`video()` throw `Exception('Not implemented')` — no v1 openapi doc in-repo to verify the endpoint shape, so no best-effort guess.
- Driver `wuzapi`: same — `file()`/`video()` throw `Exception('Not implemented')`. Wuzapi's API is a different upstream (whatsmeow-based, base64 payloads, `/chat/send/*` routes) with no docs in-repo.
- Optional flags (`forwarded()`, `duration()`, `viewOnce()`, `compress()`, `gifPlayback()`) are harmless everywhere: stored on the base class, only read by drivers whose `send()` actually forwards them. This mirrors the existing convention where `AldinokemalWhatsapp::replyMessage()` silently ignores its `$participant` argument.
- New Pest tests using `Http::fake()` cover v8's payload/endpoint per send type and assert the v1/wuzapi exceptions.

## Architecture

### `Contracts\WhatsappInterface`

Add 7 fluent methods (docblocks matching the existing style):

```php
public function file(string $file): static;
public function video(string $video): static;
public function forwarded(bool $forwarded = true): static;
public function duration(int $seconds): static;
public function viewOnce(bool $viewOnce = true): static;
public function compress(bool $compress = true): static;
public function gifPlayback(bool $gifPlayback = true): static;
```

### `Drivers\Whatsapp` (abstract base)

Add shared, driver-agnostic state + setters so v1/wuzapi don't need boilerplate:

```php
protected bool $isForwarded = false;
protected ?int $duration = null;
protected bool $viewOnce = false;
protected bool $compress = false;
protected bool $gifPlayback = false;

public function forwarded(bool $forwarded = true): static { $this->isForwarded = $forwarded; return $this; }
public function duration(int $seconds): static { $this->duration = $seconds; return $this; }
public function viewOnce(bool $viewOnce = true): static { $this->viewOnce = $viewOnce; return $this; }
public function compress(bool $compress = true): static { $this->compress = $compress; return $this; }
public function gifPlayback(bool $gifPlayback = true): static { $this->gifPlayback = $gifPlayback; return $this; }

public function file(string $file): static { throw new Exception('Not implemented'); }
public function video(string $video): static { throw new Exception('Not implemented'); }
```

`AldinokemalV8Whatsapp` overrides `file()`/`video()`. `AldinokemalWhatsapp` and `WuzapiWhatsapp` inherit everything above unchanged — they satisfy the interface with zero new code, and the optional setters are no-ops because their `send()` methods never read `$isForwarded`/`$duration`/etc.

This is a deliberate small deviation from the "each driver independently implements the full contract" pattern documented in `CLAUDE.md`, justified by genuine 3x duplication (identical exception, identical no-op state) that would otherwise exist. If this trade-off turns out to be wrong in review, the fallback is duplicating the throw + no-op setters into each of the three driver classes instead.

### `Drivers\AldinokemalV8Whatsapp`

- New private props: `?string $file = null`, `?string $video = null`.
- New private helper:
  ```php
  private function resolveMediaUrl(string $path): string
  {
      return str($path)->isUrl() ? $path : Storage::url($path);
  }
  ```
  `image()`'s inline `str($this->image)->isUrl() ? $this->image : Storage::url($this->image)` in `send()` is replaced with `$this->resolveMediaUrl($this->image)` so the same helper backs image/file/video.
- `file(string $file): static` / `video(string $video): static` — store to `$this->file` / `$this->video`, return `$this` (override the base class's throwing defaults).
- `send()` dispatch order (first non-null wins): `video` → `file` → `image` → plain `message`.
  - `/send/video` payload: `phone, caption: $this->message, reply_message_id, view_once: $this->viewOnce, video_url: resolveMediaUrl($this->video), compress: $this->compress, gif_playback: $this->gifPlayback, duration: $this->duration, is_forwarded: $this->isForwarded`
  - `/send/file` payload: `phone, caption: $this->message, reply_message_id, file_url: resolveMediaUrl($this->file), is_forwarded: $this->isForwarded, duration: $this->duration`
  - `/send/image` payload: existing fields + `view_once: $this->viewOnce, compress: $this->compress, duration: $this->duration, is_forwarded: $this->isForwarded`
  - `/send/message` payload: existing fields + `is_forwarded: $this->isForwarded, duration: $this->duration`
- `validateData()`: extend the existing `throw_if(! $this->message && ! $this->image, ...)` to `throw_if(! $this->message && ! $this->image && ! $this->file && ! $this->video, ...)`.

### `Drivers\AldinokemalWhatsapp` (v1) and `Drivers\WuzapiWhatsapp`

No code changes. They already extend `Drivers\Whatsapp`, so they inherit the throwing `file()`/`video()` and the no-op optional setters, which is sufficient to satisfy `WhatsappInterface`.

## Error handling

- Calling `file()` or `video()` on `aldinokemal` or `wuzapi` throws `Exception('Not implemented')` immediately (fail fast at the setter call, not deferred to `send()`), matching the existing style of `AldinokemalV8Whatsapp::webhookImageMimeType()`.
- `send()` on `aldinokemal_v8` throws `Exception('Target must be set')` / `Exception('Message or Image must be set')` (message text updated to reflect file/video too) via the existing `throw_unless`/`throw_if` helpers in `validateData()`.

## Testing

New Pest test file `tests/Drivers/AldinokemalV8WhatsappTest.php`:
- For each of text/image/file/video: `Http::fake()`, build the fluent chain (including optional flags), call `send()`, assert the request hit the right path with the right JSON body.
- Assert `AldinokemalWhatsapp` and `WuzapiWhatsapp` throw `Exception` when `file()`/`video()` are called.

Existing `tests/ArchTest.php` (no `dd`/`dump`/`ray`) and Pint style checks apply unchanged; no changes needed there.

## Out of scope

- Real multipart binary file uploads (`UploadedFile` support) for any of `image()`/`file()`/`video()`.
- `mentions` on `/send/message`.
- Any `send/*` endpoint other than message/image/file/video (audio, sticker, contact, link, location, poll, presence, chat-presence).
- Best-effort implementation of `file()`/`video()` for `aldinokemal` (v1) or `wuzapi`.
