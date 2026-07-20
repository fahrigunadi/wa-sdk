# aldinokemal_v8: Message Delete/Read — Design

## Context

A prior feature added `revokeMessage()`, `reactMessage()`, `updateMessage()` to `WhatsappInterface` for the `message` tag's revoke/react/update endpoints, deliberately leaving `/message/{message_id}/delete` and `/message/{message_id}/read` out of scope (the tag description only named revoke/react/update). This design closes that gap: `deleteMessage()` and `readMessage()`, for the `aldinokemal_v8` driver, reusing the exact same architecture as the already-merged revoke/react/update feature.

`.aldinokemal_v8/openapi.yaml` documents:
- `/message/{message_id}/delete` (POST): body `{ phone }` (not marked `required` in the schema, same looseness as `revoke`).
- `/message/{message_id}/read` (POST): body `{ phone }`, explicitly `required: [phone]`.

(A `/message/{message_id}/star` endpoint also exists under the same `message` tag and remains out of scope — not requested.)

## Decisions from brainstorming

Identical to the revoke/react/update feature, reused wholesale:
- Direct-action API shape: `whatsapp()->deleteMessage($messageId, $phone)` / `whatsapp()->readMessage($messageId, $phone)` hit the endpoint and return `Response` immediately.
- `phone` is a required, non-nullable `string` parameter on both methods (matching the `readMessage` schema's own `required: [phone]`, and applying the same treatment to `deleteMessage` even though its schema doesn't list `phone` as required — consistent with how `revokeMessage`/`reactMessage` were already handled).
- `aldinokemal` (v1) and `wuzapi` get both methods via the abstract `Drivers\Whatsapp` base class's throwing default (`Exception('Not implemented')`) — zero new code in either driver file.
- New Pest tests in the same `tests/Drivers/AldinokemalV8WhatsappTest.php` file.

## Architecture

### `Contracts\WhatsappInterface`

```php
public function deleteMessage(string $messageId, string $phone): Response;
public function readMessage(string $messageId, string $phone): Response;
```

### `Drivers\Whatsapp` (abstract base)

```php
public function deleteMessage(string $messageId, string $phone): Response
{
    throw new Exception('Not implemented');
}

public function readMessage(string $messageId, string $phone): Response
{
    throw new Exception('Not implemented');
}
```

### `Drivers\AldinokemalV8Whatsapp`

```php
public function deleteMessage(string $messageId, string $phone): Response
{
    return $this->request()->post("/message/{$messageId}/delete", [
        'phone' => $phone,
    ]);
}

public function readMessage(string $messageId, string $phone): Response
{
    return $this->request()->post("/message/{$messageId}/read", [
        'phone' => $phone,
    ]);
}
```

No `validateData()` involvement, no new imports (`Response` already imported in all three files from the prior feature).

## Error handling

Calling either method on `aldinokemal` or `wuzapi` throws `Exception('Not implemented')` immediately — identical to `revokeMessage()`/`reactMessage()`/`updateMessage()`.

## Testing

Extend `tests/Drivers/AldinokemalV8WhatsappTest.php`:
- Add 4 throw-assertions (`deleteMessage`/`readMessage` × `AldinokemalWhatsapp`/`WuzapiWhatsapp`) to the existing `describe('driver base defaults', ...)` block.
- Add a new `describe('AldinokemalV8Whatsapp message manipulation (delete/read)', ...)` block (or extend the existing message-manipulation block) with `Http::fake()`/`Http::assertSent()` checks for both endpoints.

## Out of scope

- `/message/{message_id}/star` — not requested, not added.
- Best-effort implementation for `aldinokemal` (v1) or `wuzapi` — both throw, same rationale as the rest of the `message` tag work.
