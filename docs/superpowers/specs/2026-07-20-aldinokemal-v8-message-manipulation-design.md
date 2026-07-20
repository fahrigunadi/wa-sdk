# aldinokemal_v8: Message Manipulation (Revoke/React/Update) — Design

## Context

`.aldinokemal_v8/openapi.yaml`'s `message` tag ("Message manipulation (revoke/react/update)") documents 5 endpoints: `/message/{message_id}/revoke`, `/message/{message_id}/delete`, `/message/{message_id}/reaction`, `/message/{message_id}/update`, `/message/{message_id}/read`. The tag description only names revoke/react/update, mirroring the earlier `send` tag ("Send Message (Text/Image/File/Video)") that undersold its own endpoint list — that precedent (scoping to exactly what the tag description names, leaving the rest out entirely) is reused here.

`WhatsappInterface` currently has no way to act on a message already sent (revoke it, react to it, edit it). This design adds exactly that, for the `aldinokemal_v8` driver, with `aldinokemal` (v1) and `wuzapi` satisfying the interface via the same throwing-default mechanism used for `file()`/`video()` in the prior send-types feature.

## Decisions from brainstorming

- Scope is exactly revoke, reaction, update. `delete` and `read` (also under the `message` tag) are out of scope — not added to the interface at all, matching the `send` tag precedent where `audio`/`sticker`/`contact`/`link`/`location`/`poll`/`presence` were excluded.
- API shape is direct-action, not fluent: `whatsapp()->revokeMessage($messageId, $phone)` hits the endpoint and returns the `Response` immediately — no `->send()` needed. This matches the existing non-fluent `getMyGroups(): Collection` method already on the interface, and differs deliberately from the `to()->message()->send()` builder used for outbound sends (revoke/react/update act on a message that already exists; there's no multi-field message to compose).
- `phone` (the chat the message lives in) is a required `string` parameter on all three new methods, not nullable — even though `openapi.yaml` doesn't list it in `required` for `revoke`/`reaction` (only `update` explicitly requires `phone`+`message`). Without knowing the chat, the SDK can't call the endpoint meaningfully; the openapi omission is treated as spec looseness, not a signal to make the parameter optional.
- `aldinokemal` (v1) and `wuzapi` get these three methods via the abstract `Drivers\Whatsapp` base class's default implementation, which throws `Exception('Not implemented')` — identical mechanism to `file()`/`video()`. Zero new code needed in either driver file.
- New Pest tests, `Http::fake()`-based, in the same `tests/Drivers/AldinokemalV8WhatsappTest.php` file used by the prior feature.

## Architecture

### `Contracts\WhatsappInterface`

Add 3 methods (docblocks matching the file's existing style):

```php
public function revokeMessage(string $messageId, string $phone): Response;
public function reactMessage(string $messageId, string $phone, string $emoji): Response;
public function updateMessage(string $messageId, string $phone, string $message): Response;
```

`Response` is `Illuminate\Http\Client\Response`, already imported in this file. No naming collision with the existing `replyMessage(string $messageId, ?string $participant = null): static` — that method sets the reply-to target on the fluent outbound builder; these three act on an already-sent message directly.

### `Drivers\Whatsapp` (abstract base)

Add default throwing implementations, same shape as the existing `file()`/`video()` defaults:

```php
public function revokeMessage(string $messageId, string $phone): Response
{
    throw new Exception('Not implemented');
}

public function reactMessage(string $messageId, string $phone, string $emoji): Response
{
    throw new Exception('Not implemented');
}

public function updateMessage(string $messageId, string $phone, string $message): Response
{
    throw new Exception('Not implemented');
}
```

`AldinokemalV8Whatsapp` overrides all three. `AldinokemalWhatsapp` (v1) and `WuzapiWhatsapp` inherit them unchanged — no new code in either file.

### `Drivers\AldinokemalV8Whatsapp`

Override the three methods, each a direct `POST` via the existing `request()` helper (Basic Auth + optional `X-Device-Id` header — already handles device scoping):

```php
public function revokeMessage(string $messageId, string $phone): Response
{
    return $this->request()->post("/message/{$messageId}/revoke", [
        'phone' => $phone,
    ]);
}

public function reactMessage(string $messageId, string $phone, string $emoji): Response
{
    return $this->request()->post("/message/{$messageId}/reaction", [
        'phone' => $phone,
        'emoji' => $emoji,
    ]);
}

public function updateMessage(string $messageId, string $phone, string $message): Response
{
    return $this->request()->post("/message/{$messageId}/update", [
        'phone' => $phone,
        'message' => $message,
    ]);
}
```

No `validateData()` involvement — these methods take all their required data as parameters, unlike the stateful `to()->message()->send()` builder, so there's no builder state to validate.

## Error handling

- Calling `revokeMessage()`/`reactMessage()`/`updateMessage()` on `aldinokemal` or `wuzapi` throws `Exception('Not implemented')` immediately, matching the existing pattern for `file()`/`video()` and `webhookImageMimeType()`.
- No new validation errors on `aldinokemal_v8` — the API itself returns `400`/`500` for bad `message_id`/`phone`, surfaced via the returned `Response` (same as every other `send()`-family method; the SDK doesn't inspect status codes itself).

## Testing

New `describe()` block in `tests/Drivers/AldinokemalV8WhatsappTest.php`:
- For each of revoke/reaction/update: `Http::fake()`, call the method directly (no fluent chain), assert the request hit the right path (`/message/{id}/revoke` etc.) with the right JSON body.
- Extend the existing "driver base defaults" describe block (or add assertions alongside it) to cover `AldinokemalWhatsapp` and `WuzapiWhatsapp` throwing `Exception` for all three new methods.

## Out of scope

- `/message/{message_id}/delete` and `/message/{message_id}/read` — not added to the interface at all.
- Best-effort implementation of revoke/react/update for `aldinokemal` (v1) or `wuzapi` — both throw, per the brainstorming decision (same rationale as `file()`/`video()`: no in-repo docs to verify either driver's actual endpoint shape).
- Any change to the fluent outbound builder (`to()`/`message()`/`image()`/etc.) — revoke/react/update are fully independent of it.
