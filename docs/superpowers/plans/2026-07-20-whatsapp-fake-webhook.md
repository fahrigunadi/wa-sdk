# WhatsappFake Phase 2 (Webhook + getMyGroups) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the 13 `webhook*()` methods and `getMyGroups()` on `WhatsappFake` real fake behavior — reading from test-injected canned state instead of throwing — completing 40 of `WhatsappInterface`'s 41 methods (`request()` stays permanently throwing, unchanged, per phase 1's design).

**Architecture:** Two new setters, `givenWebhook(array $payload): static` (merges into a `$webhookPayload` array) and `withGroups(iterable $groups): static` (cans a `Collection` for `getMyGroups()`). Each of the 13 `webhook*()` methods becomes a one-line read of `$webhookPayload` with a type-appropriate default. `WebhookRequest` needs no code changes — it already delegates to `whatsapp()->webhookXxx()`, so it picks up the fake automatically through the existing container-swap mechanism from phase 1.

**Tech Stack:** PHP 8.2, Laravel package, Pest 3 for tests, Orchestra Testbench.

## Global Constraints

- `declare(strict_types=1)` — already present in the modified file, do not remove.
- Namespace `FahriGunadi\Whatsapp\Testing` — do not change.
- No `dd()`, `dump()`, or `ray()` anywhere in `src/`.
- Laravel Pint runs on every commit via `.git-hooks/pre-commit` — code must be Pint-clean.
- `vendor/bin/phpstan analyse --memory-limit=512M` (not bare `composer analyse`).
- `request(): PendingRequest` is explicitly OUT of scope — leave it throwing, unchanged. Do not touch it.
- Do not modify `src/WebhookRequest.php`, `src/WhatsappServiceProvider.php`, or any real driver class (`AldinokemalWhatsapp`, `AldinokemalV8Whatsapp`, `WuzapiWhatsapp`, `Drivers\Whatsapp`).
- `givenWebhook()` merges into existing state (`array_merge`), it does not replace it wholesale.
- Exact `givenWebhook()` payload keys (verbatim, do not rename): `sender`, `chat`, `message_text`, `message_id`, `message_timestamp`, `pushname`, `is_group`, `is_image`, `image_mime_type`, `image`, `is_document`, `document_mime_type`, `document`.

---

### Task 1: Webhook getters + `getMyGroups()` fake behavior

**Files:**
- Modify: `src/Testing/WhatsappFake.php`
- Modify: `tests/Testing/WhatsappFakeTest.php`

**Interfaces:**
- Produces: `WhatsappFake::givenWebhook(array $payload): static`, `::withGroups(iterable $groups): static`, and real (non-throwing) implementations of all 13 `webhook*()` methods plus `getMyGroups(): Collection`. `request()` is unaffected — still throws.

- [ ] **Step 1: Write the failing test**

In `tests/Testing/WhatsappFakeTest.php`, first replace the `it('throws not implemented for methods not yet faked', ...)` test's dataset (currently lines 16-32) — remove all 14 rows except `'request'` (the 13 `webhook*` rows and the `'getMyGroups'` row are about to get real behavior). The dataset should read:

```php
])->throws(Exception::class, 'Not implemented')->with([
    'request' => ['request', []],
]);
```

Then append these tests to the end of the file:

```php
it('returns the configured webhook fields and type-appropriate defaults', function () {
    $fake = new WhatsappFake;

    expect($fake->webhookSender())->toBe('');
    expect($fake->webhookChat())->toBe('');
    expect($fake->webhookMessageText())->toBeNull();
    expect($fake->webhookMessageId())->toBeNull();
    expect($fake->webhookMessageTimestamp())->toBeNull();
    expect($fake->webhookPushname())->toBeNull();
    expect($fake->webhookIsGroup())->toBeFalse();
    expect($fake->webhookIsImage())->toBeFalse();
    expect($fake->webhookImageMimeType())->toBeNull();
    expect($fake->webhookImage())->toBeNull();
    expect($fake->webhookIsDocument())->toBeFalse();
    expect($fake->webhookDocumentMimeType())->toBeNull();
    expect($fake->webhookDocument())->toBeNull();

    $fake->givenWebhook([
        'sender' => '628123456789',
        'chat' => '628123456789@s.whatsapp.net',
        'message_text' => 'halo',
        'message_id' => '3EB089B9D6ADD58153C561',
        'message_timestamp' => '1700000000',
        'pushname' => 'Budi',
        'is_group' => true,
        'is_image' => true,
        'image_mime_type' => 'image/jpeg',
        'image' => 'https://example.com/a.jpg',
        'is_document' => true,
        'document_mime_type' => 'application/pdf',
        'document' => 'https://example.com/a.pdf',
    ]);

    expect($fake->webhookSender())->toBe('628123456789');
    expect($fake->webhookChat())->toBe('628123456789@s.whatsapp.net');
    expect($fake->webhookMessageText())->toBe('halo');
    expect($fake->webhookMessageId())->toBe('3EB089B9D6ADD58153C561');
    expect($fake->webhookMessageTimestamp())->toBe('1700000000');
    expect($fake->webhookPushname())->toBe('Budi');
    expect($fake->webhookIsGroup())->toBeTrue();
    expect($fake->webhookIsImage())->toBeTrue();
    expect($fake->webhookImageMimeType())->toBe('image/jpeg');
    expect($fake->webhookImage())->toBe('https://example.com/a.jpg');
    expect($fake->webhookIsDocument())->toBeTrue();
    expect($fake->webhookDocumentMimeType())->toBe('application/pdf');
    expect($fake->webhookDocument())->toBe('https://example.com/a.pdf');
});

it('givenWebhook() merges rather than replaces', function () {
    $fake = new WhatsappFake;

    $fake->givenWebhook(['sender' => '628123456789']);
    $fake->givenWebhook(['chat' => '628123456789@s.whatsapp.net']);

    expect($fake->webhookSender())->toBe('628123456789');
    expect($fake->webhookChat())->toBe('628123456789@s.whatsapp.net');
});

it('getMyGroups() returns an empty Collection by default', function () {
    expect((new WhatsappFake)->getMyGroups())->toBeInstanceOf(Illuminate\Support\Collection::class);
    expect((new WhatsappFake)->getMyGroups())->toBeEmpty();
});

it('getMyGroups() returns the configured groups via withGroups()', function () {
    $fake = (new WhatsappFake)->withGroups([
        ['id' => '123@g.us', 'name' => 'Group One'],
        ['id' => '456@g.us', 'name' => 'Group Two'],
    ]);

    expect($fake->getMyGroups())->toHaveCount(2);
    expect($fake->getMyGroups()->first()['name'])->toBe('Group One');
});

it('WebhookRequest reads webhook data through the fake once faked', function () {
    FahriGunadi\Whatsapp\Facades\Whatsapp::fake()->givenWebhook([
        'sender' => '628123456789',
        'chat' => '628123456789@s.whatsapp.net',
        'message_text' => 'halo',
    ]);

    $request = new FahriGunadi\Whatsapp\WebhookRequest;

    expect($request->sender())->toBe('628123456789');
    expect($request->chat())->toBe('628123456789@s.whatsapp.net');
    expect($request->messageText())->toBe('halo');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Testing/WhatsappFakeTest.php -v`
Expected: FAIL — uncaught `Exception: Not implemented` from `webhookSender()` (the first new test's first call), since the dataset test change alone doesn't implement anything yet.

- [ ] **Step 3: Implement webhook + getMyGroups fake behavior**

In `src/Testing/WhatsappFake.php`, add these two properties alongside the existing ones (e.g. right after `private array $calls = [];` on line 33):

```php
    private array $webhookPayload = [];

    private ?Collection $groups = null;
```

Add these two setters anywhere in the class body (e.g. right after `send()`, before `assertSent()`):

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

Replace the 13 throwing `webhook*()` stubs (currently lines 249-312) and the throwing `getMyGroups()` stub (currently lines 314-317) with:

```php
    public function webhookSender(): string
    {
        return $this->webhookPayload['sender'] ?? '';
    }

    public function webhookChat(): string
    {
        return $this->webhookPayload['chat'] ?? '';
    }

    public function webhookMessageText(): ?string
    {
        return $this->webhookPayload['message_text'] ?? null;
    }

    public function webhookMessageId(): ?string
    {
        return $this->webhookPayload['message_id'] ?? null;
    }

    public function webhookMessageTimestamp(): ?string
    {
        return $this->webhookPayload['message_timestamp'] ?? null;
    }

    public function webhookPushname(): ?string
    {
        return $this->webhookPayload['pushname'] ?? null;
    }

    public function webhookIsGroup(): bool
    {
        return $this->webhookPayload['is_group'] ?? false;
    }

    public function webhookIsImage(): bool
    {
        return $this->webhookPayload['is_image'] ?? false;
    }

    public function webhookImageMimeType(): ?string
    {
        return $this->webhookPayload['image_mime_type'] ?? null;
    }

    public function webhookImage(): ?string
    {
        return $this->webhookPayload['image'] ?? null;
    }

    public function webhookIsDocument(): bool
    {
        return $this->webhookPayload['is_document'] ?? false;
    }

    public function webhookDocumentMimeType(): ?string
    {
        return $this->webhookPayload['document_mime_type'] ?? null;
    }

    public function webhookDocument(): ?string
    {
        return $this->webhookPayload['document'] ?? null;
    }

    public function getMyGroups(): Collection
    {
        return $this->groups ?? collect();
    }
```

`request()` is untouched — leave it exactly as-is, still throwing.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Testing/WhatsappFakeTest.php -v`
Expected: PASS — full file green, including all pre-existing tests.

- [ ] **Step 5: Run full suite + static analysis**

Run: `composer test && vendor/bin/phpstan analyse --memory-limit=512M`
Expected: both green.

- [ ] **Step 6: Commit**

```bash
git add src/Testing/WhatsappFake.php tests/Testing/WhatsappFakeTest.php
git commit -m "feat(testing): fake webhook parsing and getMyGroups()

The 13 webhook*() methods now read from a givenWebhook(array)-
injected payload (merged, not replaced, across calls) instead of
throwing, with defaults matching each method's real return type
('' for string, null for nullable, false for bool). getMyGroups()
returns a withGroups()-configured Collection, empty by default.
WebhookRequest needs no changes — it already delegates through the
container, verified by a smoke test. request() remains permanently
throwing; this completes 40 of WhatsappInterface's 41 methods."
```

---

## Post-plan checklist (manual, not a task)

- Re-read `docs/superpowers/specs/2026-07-20-whatsapp-fake-webhook-design.md` "Out of scope" section and confirm `request()` and `WebhookRequest.php` were not touched.
- `WhatsappFake` now has real behavior for 40/41 `WhatsappInterface` methods — only `request()` remains, and it's expected to stay that way permanently (no future-phase plan needed for it).
- Consider a README section documenting `Whatsapp::fake()` + `givenWebhook()`/`withGroups()` usage — not part of this plan.
