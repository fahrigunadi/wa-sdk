# aldinokemal_v8: Message Star/Unstar/Forward/Download — Design

## Context

The `message` tag in `.aldinokemal_v8/openapi.yaml` has 9 endpoints. Two prior, already-merged features covered 5 of them (`revoke`, `delete`, `reaction`, `update`, `read`). This design covers the remaining 4: `star`, `unstar`, `forward`, `download` — completing the entire `message` tag for the `aldinokemal_v8` driver.

Endpoint shapes (from `openapi.yaml`):
- `/message/{message_id}/star` (POST): body `{ phone }`, `required: [phone]`.
- `/message/{message_id}/unstar` (POST): body `{ phone }`, `required: [phone]`.
- `/message/{message_id}/forward` (POST): body `{ phone (required), duration (optional int), force_reupload (optional bool) }`.
- `/message/{message_id}/download` (GET, not POST): query params `message_id` (path) and `phone` (query, required). Response body is JSON describing where the downloaded media was saved (`file_path`, `file_url`, `file_size`, etc.), not the binary content itself.

## Decisions from brainstorming

Same architecture as the prior `message`-tag features, reused wholesale:
- Direct-action API shape, `aldinokemal_v8`-only implementation, `aldinokemal`/`wuzapi` satisfy the interface via the abstract base class's throwing default.
- `downloadMessage()` naming (not `downloadMessageMedia()`) — consistent with the `*Message()` naming of all 7 sibling methods already on the interface.
- `forwardMessage()`'s two optional fields (`duration`, `force_reupload`) are plain method parameters (`?int $duration = null`, `bool $forceReupload = false`), not reused from the fluent outbound-send builder's `->duration()` state (`Drivers\Whatsapp::$duration`). Reusing that shared mutable property would conflate two unrelated call paths (the fluent `to()->message()->send()` builder vs. this self-contained direct action) for a superficial field-name match; keeping every value forwardMessage() needs as its own parameter matches how `revokeMessage()`/`reactMessage()`/`updateMessage()`/`deleteMessage()`/`readMessage()` already work — no builder state involved.
- `downloadMessage()` returns the raw `Response`, like every other method in this family — no in-driver JSON parsing/DTO extraction. This matches the established convention (only `getMyGroups()` parses; everything in the `message`-tag family returns `Response` untouched).
- `downloadMessage()` issues a `GET` (not `POST`) with `phone` as a query parameter, not a JSON body — the only method in this family that isn't a POST.

## Architecture

### `Contracts\WhatsappInterface`

```php
public function starMessage(string $messageId, string $phone): Response;
public function unstarMessage(string $messageId, string $phone): Response;
public function forwardMessage(string $messageId, string $phone, ?int $duration = null, bool $forceReupload = false): Response;
public function downloadMessage(string $messageId, string $phone): Response;
```

### `Drivers\Whatsapp` (abstract base)

Default throwing implementations, same shape as the existing `revokeMessage()`/etc. defaults:

```php
public function starMessage(string $messageId, string $phone): Response
{
    throw new Exception('Not implemented');
}

public function unstarMessage(string $messageId, string $phone): Response
{
    throw new Exception('Not implemented');
}

public function forwardMessage(string $messageId, string $phone, ?int $duration = null, bool $forceReupload = false): Response
{
    throw new Exception('Not implemented');
}

public function downloadMessage(string $messageId, string $phone): Response
{
    throw new Exception('Not implemented');
}
```

`AldinokemalV8Whatsapp` overrides all four. `AldinokemalWhatsapp` (v1) and `WuzapiWhatsapp` inherit them unchanged.

### `Drivers\AldinokemalV8Whatsapp`

```php
public function starMessage(string $messageId, string $phone): Response
{
    return $this->request()->post("/message/{$messageId}/star", [
        'phone' => $phone,
    ]);
}

public function unstarMessage(string $messageId, string $phone): Response
{
    return $this->request()->post("/message/{$messageId}/unstar", [
        'phone' => $phone,
    ]);
}

public function forwardMessage(string $messageId, string $phone, ?int $duration = null, bool $forceReupload = false): Response
{
    return $this->request()->post("/message/{$messageId}/forward", [
        'phone' => $phone,
        'duration' => $duration,
        'force_reupload' => $forceReupload,
    ]);
}

public function downloadMessage(string $messageId, string $phone): Response
{
    return $this->request()->get("/message/{$messageId}/download", [
        'phone' => $phone,
    ]);
}
```

No `validateData()` involvement, no new imports.

## Error handling

Calling any of the four on `aldinokemal` or `wuzapi` throws `Exception('Not implemented')` immediately, matching the rest of the `message`-tag methods.

## Testing

Extend `tests/Drivers/AldinokemalV8WhatsappTest.php`:
- 8 throw-assertions (4 methods × `AldinokemalWhatsapp`/`WuzapiWhatsapp`) added to the existing `describe('driver base defaults', ...)` block.
- 4 `Http::fake()`/`Http::assertSent()` checks added to the existing `describe('AldinokemalV8Whatsapp message manipulation', ...)` block — the `downloadMessage()` test must assert a `GET` request (via `$request->isGet()` or method inspection) with `phone` present as a query parameter, not a JSON body key, since `Http::fake()`'s `assertSent` closure receives the full request regardless of body-encoding.

## Out of scope

- Actually retrieving/streaming the downloaded media's binary bytes — `downloadMessage()` only returns the API's JSON description of where the file was saved (`file_path`/`file_url`), matching what `/message/{message_id}/download` itself returns. Fetching the binary from `file_url` is a separate, unrequested concern.
- Anything outside the `message` tag (this completes it: 9/9 endpoints across this and the two prior features).
