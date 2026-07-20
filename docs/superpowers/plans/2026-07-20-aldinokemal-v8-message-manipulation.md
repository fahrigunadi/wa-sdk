# aldinokemal_v8 Message Manipulation (Revoke/React/Update) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `revokeMessage()`, `reactMessage()`, and `updateMessage()` to `WhatsappInterface` as direct-action methods (they hit the API and return the `Response` immediately — no fluent `->send()` step), fully implemented for the `aldinokemal_v8` driver against `/message/{message_id}/revoke`, `/message/{message_id}/reaction`, and `/message/{message_id}/update`, while `aldinokemal` (v1) and `wuzapi` satisfy the interface via the abstract base class's throwing default — zero new code in either driver file.

**Architecture:** `Contracts\WhatsappInterface` grows 3 new methods, each `(...): Response`. `Drivers\Whatsapp` (abstract base) gets default implementations that `throw new Exception('Not implemented')`, mirroring the existing `file()`/`video()` defaults already there. `Drivers\AldinokemalV8Whatsapp` overrides all three with a direct `$this->request()->post(...)` call each — no builder state, no `validateData()` involvement, since every required value arrives as a method parameter.

**Tech Stack:** PHP 8.2, Laravel package (Illuminate HTTP client), Pest 3 for tests (`Http::fake()` / `Http::assertSent()`), Orchestra Testbench.

## Global Constraints

- `declare(strict_types=1)` at the top of every modified file (already present — do not remove).
- Namespace `FahriGunadi\Whatsapp\Drivers\*` (lowercase `Whatsapp`, matching `composer.json`'s PSR-4 map) — do not reintroduce the capital-`A` `WhatsApp` casing that was fixed in the prior feature's prerequisite commit.
- No `dd()`, `dump()`, or `ray()` anywhere in `src/` (enforced by `tests/ArchTest.php`).
- Laravel Pint runs on every commit via the `.git-hooks/pre-commit` hook (`pint --test`) — code must already be Pint-clean before committing; if the hook fails, run `composer format` and re-stage.
- `Illuminate\Http\Client\Response` is already imported in `src/Contracts/WhatsappInterface.php` (used by the existing `send(): Response` method) — no new `use` statement needed there.
- `phone` is a required (non-nullable) `string` parameter on all three new methods, even though `openapi.yaml` doesn't list it as `required` for `revoke`/`reaction` — this is a deliberate departure from the spec's literal schema, decided during brainstorming (see the design doc's "Decisions" section).
- Scope is exactly: `revokeMessage()`, `reactMessage()`, `updateMessage()`. Do NOT add `deleteMessage()` or `readMessage()` — both exist under the same `message` tag in `openapi.yaml` but are explicitly out of scope (see the design spec's "Out of scope" section).
- PHPStan's default memory limit (128M, from `phpstan.neon.dist`) is too low in this environment and crashes mid-run; always pass `--memory-limit=512M` when running `vendor/bin/phpstan analyse` directly. `composer analyse` invokes the bare command without the flag, so prefer the direct `vendor/bin/phpstan analyse --memory-limit=512M` invocation over `composer analyse` when verifying this plan's tasks.

---

### Task 1: Interface contract + shared base-class throwing defaults

**Files:**
- Modify: `src/Contracts/WhatsappInterface.php`
- Modify: `src/Drivers/Whatsapp.php`
- Modify: `tests/Drivers/AldinokemalV8WhatsappTest.php`

**Interfaces:**
- Produces: `WhatsappInterface::revokeMessage(string $messageId, string $phone): Response`, `::reactMessage(string $messageId, string $phone, string $emoji): Response`, `::updateMessage(string $messageId, string $phone, string $message): Response`. All three throw `Exception('Not implemented')` by default via `Drivers\Whatsapp`, inherited as-is by `AldinokemalWhatsapp` and `WuzapiWhatsapp`.

- [ ] **Step 1: Write the failing test**

Add this to `tests/Drivers/AldinokemalV8WhatsappTest.php`, inside the existing `describe('driver base defaults', function () { ... });` block (currently spanning lines 6-33) — insert these 3 `it(...)` calls right after the existing `it('throws not implemented for video() on wuzapi', ...)` block (after line 21) and before the `it('stores optional flags fluently ...')` block (line 23):

```php
    it('throws not implemented for revokeMessage() on aldinokemal v1', function () {
        (new AldinokemalWhatsapp)->revokeMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for reactMessage() on aldinokemal v1', function () {
        (new AldinokemalWhatsapp)->reactMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net', '🙏');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for updateMessage() on aldinokemal v1', function () {
        (new AldinokemalWhatsapp)->updateMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net', 'edited text');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for revokeMessage() on wuzapi', function () {
        (new WuzapiWhatsapp)->revokeMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for reactMessage() on wuzapi', function () {
        (new WuzapiWhatsapp)->reactMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net', '🙏');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for updateMessage() on wuzapi', function () {
        (new WuzapiWhatsapp)->updateMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net', 'edited text');
    })->throws(Exception::class, 'Not implemented');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Drivers/AldinokemalV8WhatsappTest.php -v`
Expected: FAIL — `Call to undefined method FahriGunadi\Whatsapp\Drivers\AldinokemalWhatsapp::revokeMessage()` (or whichever of the 6 new assertions Pest reaches first).

- [ ] **Step 3: Add the 3 methods to the interface**

In `src/Contracts/WhatsappInterface.php`, insert immediately after the existing `gifPlayback()` method (after its closing `;` on line 95, right before the `/** Prepare the HTTP client request instance. */` docblock on line 97) and before the `request()` method:

```php
    /**
     * Revoke (delete for everyone) a previously sent message.
     *
     * @param  string  $messageId  The ID of the message to revoke.
     * @param  string  $phone  The chat (phone/group JID) the message was sent in.
     */
    public function revokeMessage(string $messageId, string $phone): Response;

    /**
     * React to a message with an emoji.
     *
     * @param  string  $messageId  The ID of the message to react to.
     * @param  string  $phone  The chat (phone/group JID) the message was sent in.
     * @param  string  $emoji  The emoji to react with.
     */
    public function reactMessage(string $messageId, string $phone, string $emoji): Response;

    /**
     * Edit the text of a previously sent message.
     *
     * @param  string  $messageId  The ID of the message to edit.
     * @param  string  $phone  The chat (phone/group JID) the message was sent in.
     * @param  string  $message  The new message text.
     */
    public function updateMessage(string $messageId, string $phone, string $message): Response;
```

- [ ] **Step 4: Add throwing defaults to the abstract base class**

In `src/Drivers/Whatsapp.php`, the imports are currently (lines 7-10):

```php
use Exception;
use FahriGunadi\Whatsapp\Traits\Logging;
use Illuminate\Support\Collection;
use InvalidArgumentException;
```

Insert `use Illuminate\Http\Client\Response;` between the `Logging` and `Illuminate\Support\Collection` lines, to keep alphabetical order (Pint's `ordered_imports` fixer enforces this and the pre-commit hook runs `pint --test`, which fails — not just reformats — on misordered imports):

```php
use Exception;
use FahriGunadi\Whatsapp\Traits\Logging;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use InvalidArgumentException;
```

Then add these 3 methods right after the existing `gifPlayback()` method (after its closing `}` on line 129) and before the `formatPhone()` method:

```php
    /**
     * Revoke (delete for everyone) a previously sent message.
     *
     * Not implemented by default; only drivers whose backend supports
     * message revocation override this.
     *
     * @param  string  $messageId  The ID of the message to revoke.
     * @param  string  $phone  The chat (phone/group JID) the message was sent in.
     *
     * @throws Exception Always, unless overridden by a driver.
     */
    public function revokeMessage(string $messageId, string $phone): Response
    {
        throw new Exception('Not implemented');
    }

    /**
     * React to a message with an emoji.
     *
     * Not implemented by default; only drivers whose backend supports
     * message reactions override this.
     *
     * @param  string  $messageId  The ID of the message to react to.
     * @param  string  $phone  The chat (phone/group JID) the message was sent in.
     * @param  string  $emoji  The emoji to react with.
     *
     * @throws Exception Always, unless overridden by a driver.
     */
    public function reactMessage(string $messageId, string $phone, string $emoji): Response
    {
        throw new Exception('Not implemented');
    }

    /**
     * Edit the text of a previously sent message.
     *
     * Not implemented by default; only drivers whose backend supports
     * message editing override this.
     *
     * @param  string  $messageId  The ID of the message to edit.
     * @param  string  $phone  The chat (phone/group JID) the message was sent in.
     * @param  string  $message  The new message text.
     *
     * @throws Exception Always, unless overridden by a driver.
     */
    public function updateMessage(string $messageId, string $phone, string $message): Response
    {
        throw new Exception('Not implemented');
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Drivers/AldinokemalV8WhatsappTest.php -v`
Expected: PASS — all tests including the pre-existing ones.

- [ ] **Step 6: Run full suite + static analysis**

Run: `composer test && vendor/bin/phpstan analyse --memory-limit=512M`
Expected: both green. (`AldinokemalV8Whatsapp` does not yet implement the 3 new methods itself, but it inherits the base class's throwing defaults, so it still satisfies `WhatsappInterface` — no fatal errors.)

- [ ] **Step 7: Commit**

```bash
git add src/Contracts/WhatsappInterface.php src/Drivers/Whatsapp.php tests/Drivers/AldinokemalV8WhatsappTest.php
git commit -m "feat(whatsapp): add revoke/react/update message contract

Add revokeMessage(), reactMessage(), and updateMessage() to
WhatsappInterface as direct-action methods (hit the API and return
the Response immediately, no fluent ->send() step). Default
implementations on the abstract Whatsapp base class throw Not
implemented, mirroring file()/video(), so aldinokemal (v1) and
wuzapi satisfy the interface without new code."
```

---

### Task 2: `aldinokemal_v8` — implement revoke/react/update

**Files:**
- Modify: `src/Drivers/AldinokemalV8Whatsapp.php`
- Modify: `tests/Drivers/AldinokemalV8WhatsappTest.php`

**Interfaces:**
- Consumes: `$this->request(): PendingRequest` (existing method on `AldinokemalV8Whatsapp`, already handles Basic Auth + optional `X-Device-Id` header — no changes needed to it).
- Produces: `AldinokemalV8Whatsapp::revokeMessage(string $messageId, string $phone): Response`, `::reactMessage(string $messageId, string $phone, string $emoji): Response`, `::updateMessage(string $messageId, string $phone, string $message): Response` (all override the base class's throwing defaults from Task 1).

- [ ] **Step 1: Write the failing tests**

Append this new `describe()` block to the end of `tests/Drivers/AldinokemalV8WhatsappTest.php` (after the closing `});` of the existing `describe('AldinokemalV8Whatsapp::send() text', ...)` block, which is currently the last block in the file):

```php
describe('AldinokemalV8Whatsapp message manipulation', function () {
    it('posts to /message/{message_id}/revoke with the phone', function () {
        Http::fake(['*' => Http::response(['status' => 200], 200)]);

        (new AldinokemalV8Whatsapp)->revokeMessage(
            '3EB089B9D6ADD58153C561',
            '6289685028129@s.whatsapp.net'
        );

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://gowa.example.com/message/3EB089B9D6ADD58153C561/revoke'
                && $request['phone'] === '6289685028129@s.whatsapp.net';
        });
    });

    it('posts to /message/{message_id}/reaction with the phone and emoji', function () {
        Http::fake(['*' => Http::response(['status' => 200], 200)]);

        (new AldinokemalV8Whatsapp)->reactMessage(
            '3EB089B9D6ADD58153C561',
            '6289685028129@s.whatsapp.net',
            '🙏'
        );

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://gowa.example.com/message/3EB089B9D6ADD58153C561/reaction'
                && $request['phone'] === '6289685028129@s.whatsapp.net'
                && $request['emoji'] === '🙏';
        });
    });

    it('posts to /message/{message_id}/update with the phone and new message', function () {
        Http::fake(['*' => Http::response(['status' => 200], 200)]);

        (new AldinokemalV8Whatsapp)->updateMessage(
            '3EB089B9D6ADD58153C561',
            '6289685028129@s.whatsapp.net',
            'edited text'
        );

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://gowa.example.com/message/3EB089B9D6ADD58153C561/update'
                && $request['phone'] === '6289685028129@s.whatsapp.net'
                && $request['message'] === 'edited text';
        });
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Drivers/AldinokemalV8WhatsappTest.php -v`
Expected: FAIL — uncaught `Exception: Not implemented` from the inherited base `revokeMessage()` (or whichever method Pest reaches first).

- [ ] **Step 3: Implement the 3 methods**

In `src/Drivers/AldinokemalV8Whatsapp.php`, insert these 3 methods right after the existing `file()` method (after its closing `}` on line 79) and before `request()`:

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

`Response` is already imported in this file (`use Illuminate\Http\Client\Response;`, line 11) — no new `use` statement needed.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Drivers/AldinokemalV8WhatsappTest.php -v`
Expected: PASS — full file green, including all pre-existing tests.

- [ ] **Step 5: Run full suite + static analysis**

Run: `composer test && vendor/bin/phpstan analyse --memory-limit=512M`
Expected: both green.

- [ ] **Step 6: Commit**

```bash
git add src/Drivers/AldinokemalV8Whatsapp.php tests/Drivers/AldinokemalV8WhatsappTest.php
git commit -m "feat(aldinokemal-v8): implement revoke/react/update

revokeMessage(), reactMessage(), and updateMessage() each POST
directly to /message/{message_id}/{revoke,reaction,update} via the
existing request() client (Basic Auth + optional X-Device-Id), no
builder state or validateData() involved since every value arrives
as a method parameter."
```

---

## Post-plan checklist (manual, not a task)

- Re-read `docs/superpowers/specs/2026-07-20-aldinokemal-v8-message-manipulation-design.md` "Out of scope" section and confirm `deleteMessage()`/`readMessage()` were not added anywhere.
- `README.md`'s `## Usage` section doesn't cover any of `send()`'s optional flags either yet (from the prior feature) — if a future PR adds a `file()`/`video()` usage snippet, consider covering `revokeMessage()`/`reactMessage()`/`updateMessage()` in the same pass.
