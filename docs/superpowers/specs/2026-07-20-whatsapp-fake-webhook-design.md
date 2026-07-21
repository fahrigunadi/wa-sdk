# WhatsappFake Phase 2: Webhook Parsing + getMyGroups — Design

## Context

Phase 1 (already merged) built `FahriGunadi\Whatsapp\Testing\WhatsappFake`, giving real fake behavior to the outbound send builder and the 9 message-manipulation methods, with `Whatsapp::fake()` swapping it into the container. 15 of `WhatsappInterface`'s 41 methods were left deliberately throwing `Exception('Not implemented')`: 13 `webhook*()` methods, `getMyGroups()`, and `request()`. This phase covers 14 of those 15 — `request()` stays permanently throwing (established in phase 1: none of `WhatsappFake`'s own methods ever call it, and there's no legitimate reason for a consumer to call it directly on a fake).

The 13 `webhook*()` methods are architecturally different from everything phase 1 built: real drivers read them live off the *current incoming HTTP request* (e.g. `request()->json('payload.from')`), not from an API response. A fake has no real incoming webhook request in a typical unit test, so it needs a way for the test to inject canned webhook data up front.

## Decisions from brainstorming

- **Webhook injection API:** a single `givenWebhook(array $payload): static` method, not 13 individual typed setters. Matches the fake's existing minimalist, array-based state style (`$sent`, `$calls` are already plain arrays); the type-safety tradeoff (possible key typos) was accepted in exchange for a much smaller API surface.
- **Merge, not replace:** `givenWebhook()` merges into existing state (`array_merge($this->webhookPayload, $payload)`) rather than replacing it wholesale — lets a test set common defaults once (e.g. in `beforeEach()`) and override just the fields a specific test cares about across multiple calls.
- **`getMyGroups()` gets its own setter,** `withGroups(iterable $groups): static`, separate from `givenWebhook()` — it's conceptually different (an outbound API *read*, not inbound webhook data), so bundling it into the webhook payload array would conflate two unrelated concerns.
- **Per-field defaults match each method's real return type:** `string`-typed methods (`webhookSender`, `webhookChat`) default to `''`; nullable methods default to `null`; `bool`-typed methods default to `false`. `getMyGroups()` defaults to an empty `Collection`.
- **`WebhookRequest` needs zero changes.** It already delegates every method to `whatsapp()->webhookXxx()` (established architecture, documented in `CLAUDE.md`), so once `WhatsappFake` provides real behavior for those methods, `WebhookRequest` picks it up automatically through the container swap — no new integration code, no new tests of `WebhookRequest` itself needed (out of scope; already covered structurally by phase 1's swap mechanism).
- **`request()` is explicitly OUT of scope** for this phase (and likely permanently) — carried over from the phase 1 design.

## Architecture

### `src/Testing/WhatsappFake.php` (existing file, extended)

New state:

```php
private array $webhookPayload = [];
private ?Collection $groups = null;
```

New setters:

```php
public function givenWebhook(array $payload): static
{
    $this->webhookPayload = array_merge($this->webhookPayload, $payload);

    return $this;
}

public function withGroups(iterable $groups): static
{
    $this->groups = collect($groups);

    return $this;
}
```

Replace the 13 throwing `webhook*()` stubs and `getMyGroups()` with:

```php
public function webhookSender(): string { return $this->webhookPayload['sender'] ?? ''; }
public function webhookChat(): string { return $this->webhookPayload['chat'] ?? ''; }
public function webhookMessageText(): ?string { return $this->webhookPayload['message_text'] ?? null; }
public function webhookMessageId(): ?string { return $this->webhookPayload['message_id'] ?? null; }
public function webhookMessageTimestamp(): ?string { return $this->webhookPayload['message_timestamp'] ?? null; }
public function webhookPushname(): ?string { return $this->webhookPayload['pushname'] ?? null; }
public function webhookIsGroup(): bool { return $this->webhookPayload['is_group'] ?? false; }
public function webhookIsImage(): bool { return $this->webhookPayload['is_image'] ?? false; }
public function webhookImageMimeType(): ?string { return $this->webhookPayload['image_mime_type'] ?? null; }
public function webhookImage(): ?string { return $this->webhookPayload['image'] ?? null; }
public function webhookIsDocument(): bool { return $this->webhookPayload['is_document'] ?? false; }
public function webhookDocumentMimeType(): ?string { return $this->webhookPayload['document_mime_type'] ?? null; }
public function webhookDocument(): ?string { return $this->webhookPayload['document'] ?? null; }

public function getMyGroups(): Collection { return $this->groups ?? collect(); }
```

`request()` remains unchanged (still throws) — no code in this phase touches it.

### Payload key reference

| Method | `givenWebhook()` key | Type | Default |
|---|---|---|---|
| `webhookSender()` | `sender` | `string` | `''` |
| `webhookChat()` | `chat` | `string` | `''` |
| `webhookMessageText()` | `message_text` | `?string` | `null` |
| `webhookMessageId()` | `message_id` | `?string` | `null` |
| `webhookMessageTimestamp()` | `message_timestamp` | `?string` | `null` |
| `webhookPushname()` | `pushname` | `?string` | `null` |
| `webhookIsGroup()` | `is_group` | `bool` | `false` |
| `webhookIsImage()` | `is_image` | `bool` | `false` |
| `webhookImageMimeType()` | `image_mime_type` | `?string` | `null` |
| `webhookImage()` | `image` | `?string` | `null` |
| `webhookIsDocument()` | `is_document` | `bool` | `false` |
| `webhookDocumentMimeType()` | `document_mime_type` | `?string` | `null` |
| `webhookDocument()` | `document` | `?string` | `null` |

## Error handling

No new error paths — every method now has a real return value (possibly a default), never a thrown exception, matching the fact that these are pure state readers with no failure mode of their own.

## Testing

Extend `tests/Testing/WhatsappFakeTest.php`:
- Remove the 14 rows (13 webhook + `getMyGroups`) from the existing "throws not implemented" dataset — they're getting real behavior now.
- Each of the 13 `webhook*()` methods: verify it returns the configured value when set via `givenWebhook()`, and its correct type-appropriate default when not set.
- `givenWebhook()` called twice with different keys merges rather than replaces (a field set in the first call survives a second call that only sets other fields).
- `getMyGroups()` returns the configured `Collection` (via `withGroups()`) and an empty `Collection` by default.
- A basic `WebhookRequest`-integration smoke test: after `Whatsapp::fake()->givenWebhook([...])`, a `WebhookRequest` instance's `sender()`/`chat()`/`messageText()` (etc.) return the configured values — proves the whole chain (facade swap → `whatsapp()` helper → `WebhookRequest` delegation) works end-to-end, not just the fake in isolation.

## Out of scope

- `request(): PendingRequest` — stays permanently throwing (see phase 1 design and the "Decisions" section above).
- Any change to `WebhookRequest`, `WhatsappServiceProvider`, or any real driver class.
- Validation of `givenWebhook()`'s payload keys (e.g. warning on an unrecognized key/typo) — YAGNI for this phase; the array-based API's type-safety tradeoff was already accepted during brainstorming.
