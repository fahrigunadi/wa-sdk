# aldinokemal_v8 Message Delete/Read Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `deleteMessage()` and `readMessage()` to `WhatsappInterface` as direct-action methods (hit the API, return `Response` immediately, no fluent `->send()` step), fully implemented for the `aldinokemal_v8` driver against `/message/{message_id}/delete` and `/message/{message_id}/read`, while `aldinokemal` (v1) and `wuzapi` satisfy the interface via the abstract base class's throwing default — zero new code in either driver file.

**Architecture:** Identical to the already-merged revoke/react/update feature this one extends. `Contracts\WhatsappInterface` grows 2 new methods, each `(...): Response`. `Drivers\Whatsapp` (abstract base) gets 2 more throwing defaults, same shape as the existing `revokeMessage()`/`reactMessage()`/`updateMessage()` defaults already there. `Drivers\AldinokemalV8Whatsapp` overrides both with a direct `$this->request()->post(...)` call each — no builder state, no `validateData()` involvement.

**Tech Stack:** PHP 8.2, Laravel package (Illuminate HTTP client), Pest 3 for tests (`Http::fake()` / `Http::assertSent()`), Orchestra Testbench.

## Global Constraints

- `declare(strict_types=1)` at the top of every modified file (already present — do not remove).
- Namespace `FahriGunadi\Whatsapp\Drivers\*` (lowercase `Whatsapp`, matching `composer.json`'s PSR-4 map) — do not change.
- No `dd()`, `dump()`, or `ray()` anywhere in `src/` (enforced by `tests/ArchTest.php`).
- Laravel Pint runs on every commit via the `.git-hooks/pre-commit` hook (`pint --test`) — code must already be Pint-clean before committing; if the hook fails, run `composer format` and re-stage.
- `Illuminate\Http\Client\Response` is already imported in all three files this plan touches (`WhatsappInterface.php`, `Whatsapp.php`, `AldinokemalV8Whatsapp.php`) — no new `use` statement needed anywhere.
- `phone` is a required (non-nullable) `string` parameter on both new methods, matching the treatment already given to `revokeMessage()`/`reactMessage()`/`updateMessage()`.
- Scope is exactly: `deleteMessage()`, `readMessage()`. Do NOT add `starMessage()` — it exists under the same `message` tag in `openapi.yaml` but is out of scope per the design spec.
- PHPStan's default memory limit (128M, from `phpstan.neon.dist`) is too low in this environment and crashes mid-run; always pass `--memory-limit=512M`: run `vendor/bin/phpstan analyse --memory-limit=512M` directly rather than bare `composer analyse`.

---

### Task 1: Interface contract + shared base-class throwing defaults

**Files:**
- Modify: `src/Contracts/WhatsappInterface.php`
- Modify: `src/Drivers/Whatsapp.php`
- Modify: `tests/Drivers/AldinokemalV8WhatsappTest.php`

**Interfaces:**
- Produces: `WhatsappInterface::deleteMessage(string $messageId, string $phone): Response`, `::readMessage(string $messageId, string $phone): Response`. Both throw `Exception('Not implemented')` by default via `Drivers\Whatsapp`, inherited as-is by `AldinokemalWhatsapp` and `WuzapiWhatsapp`.

- [ ] **Step 1: Write the failing test**

In `tests/Drivers/AldinokemalV8WhatsappTest.php`, inside the existing `describe('driver base defaults', function () { ... });` block, insert these 4 `it(...)` calls right after the existing `it('throws not implemented for updateMessage() on wuzapi', ...)` block (currently ending at line 45) and before the `it('stores optional flags fluently ...')` block (currently starting at line 47):

```php
    it('throws not implemented for deleteMessage() on aldinokemal v1', function () {
        (new AldinokemalWhatsapp)->deleteMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for readMessage() on aldinokemal v1', function () {
        (new AldinokemalWhatsapp)->readMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for deleteMessage() on wuzapi', function () {
        (new WuzapiWhatsapp)->deleteMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for readMessage() on wuzapi', function () {
        (new WuzapiWhatsapp)->readMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');
    })->throws(Exception::class, 'Not implemented');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Drivers/AldinokemalV8WhatsappTest.php -v`
Expected: FAIL — `Call to undefined method FahriGunadi\Whatsapp\Drivers\AldinokemalWhatsapp::deleteMessage()` (or whichever of the 4 new assertions Pest reaches first).

- [ ] **Step 3: Add the 2 methods to the interface**

In `src/Contracts/WhatsappInterface.php`, insert immediately after the existing `updateMessage()` method (after its closing `;` on line 121) and before the `request()` method (docblock starting line 123):

```php
    /**
     * Delete a previously sent message from the local chat view.
     *
     * @param  string  $messageId  The ID of the message to delete.
     * @param  string  $phone  The chat (phone/group JID) the message was sent in.
     */
    public function deleteMessage(string $messageId, string $phone): Response;

    /**
     * Mark a message as read.
     *
     * @param  string  $messageId  The ID of the message to mark as read.
     * @param  string  $phone  The chat (phone/group JID) the message was sent in.
     */
    public function readMessage(string $messageId, string $phone): Response;
```

- [ ] **Step 4: Add throwing defaults to the abstract base class**

In `src/Drivers/Whatsapp.php`, insert these 2 methods right after the existing `updateMessage()` method (after its closing `}` on line 180) and before the `formatPhone()` method's docblock (starting line 182):

```php
    /**
     * Delete a previously sent message from the local chat view.
     *
     * Not implemented by default; only drivers whose backend supports
     * message deletion override this.
     *
     * @param  string  $messageId  The ID of the message to delete.
     * @param  string  $phone  The chat (phone/group JID) the message was sent in.
     *
     * @throws Exception Always, unless overridden by a driver.
     */
    public function deleteMessage(string $messageId, string $phone): Response
    {
        throw new Exception('Not implemented');
    }

    /**
     * Mark a message as read.
     *
     * Not implemented by default; only drivers whose backend supports
     * read receipts override this.
     *
     * @param  string  $messageId  The ID of the message to mark as read.
     * @param  string  $phone  The chat (phone/group JID) the message was sent in.
     *
     * @throws Exception Always, unless overridden by a driver.
     */
    public function readMessage(string $messageId, string $phone): Response
    {
        throw new Exception('Not implemented');
    }
```

No new `use` statement is needed — `Illuminate\Http\Client\Response` is already imported in this file (from the prior revoke/react/update feature).

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Drivers/AldinokemalV8WhatsappTest.php -v`
Expected: PASS — all tests including the pre-existing ones.

- [ ] **Step 6: Run full suite + static analysis**

Run: `composer test && vendor/bin/phpstan analyse --memory-limit=512M`
Expected: both green. (`AldinokemalV8Whatsapp` does not yet implement the 2 new methods itself, but it inherits the base class's throwing defaults, so it still satisfies `WhatsappInterface` — no fatal errors.)

- [ ] **Step 7: Commit**

```bash
git add src/Contracts/WhatsappInterface.php src/Drivers/Whatsapp.php tests/Drivers/AldinokemalV8WhatsappTest.php
git commit -m "feat(whatsapp): add delete/read message contract

Add deleteMessage() and readMessage() to WhatsappInterface as
direct-action methods, closing the delete/read gap left out of the
earlier revoke/react/update feature. Default implementations on the
abstract Whatsapp base class throw Not implemented, mirroring the
rest of the message-manipulation methods, so aldinokemal (v1) and
wuzapi satisfy the interface without new code."
```

---

### Task 2: `aldinokemal_v8` — implement delete/read

**Files:**
- Modify: `src/Drivers/AldinokemalV8Whatsapp.php`
- Modify: `tests/Drivers/AldinokemalV8WhatsappTest.php`

**Interfaces:**
- Consumes: `$this->request(): PendingRequest` (existing method, unchanged).
- Produces: `AldinokemalV8Whatsapp::deleteMessage(string $messageId, string $phone): Response`, `::readMessage(string $messageId, string $phone): Response` (both override the base class's throwing defaults from Task 1).

- [ ] **Step 1: Write the failing tests**

In `tests/Drivers/AldinokemalV8WhatsappTest.php`, add these 2 `it(...)` blocks inside the existing `describe('AldinokemalV8Whatsapp message manipulation', function () { ... });` block (currently ending with the `updateMessage` test at line 240, closing `});` at line 241) — insert them right after the `it('posts to /message/{message_id}/update with the phone and new message', ...)` block and before the closing `});` of the `describe`:

```php
    it('posts to /message/{message_id}/delete with the phone', function () {
        Http::fake(['*' => Http::response(['status' => 200], 200)]);

        (new AldinokemalV8Whatsapp)->deleteMessage(
            '3EB089B9D6ADD58153C561',
            '6289685028129@s.whatsapp.net'
        );

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://gowa.example.com/message/3EB089B9D6ADD58153C561/delete'
                && $request['phone'] === '6289685028129@s.whatsapp.net';
        });
    });

    it('posts to /message/{message_id}/read with the phone', function () {
        Http::fake(['*' => Http::response(['status' => 200], 200)]);

        (new AldinokemalV8Whatsapp)->readMessage(
            '3EB089B9D6ADD58153C561',
            '6289685028129@s.whatsapp.net'
        );

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://gowa.example.com/message/3EB089B9D6ADD58153C561/read'
                && $request['phone'] === '6289685028129@s.whatsapp.net';
        });
    });
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Drivers/AldinokemalV8WhatsappTest.php -v`
Expected: FAIL — uncaught `Exception: Not implemented` from the inherited base `deleteMessage()` (or `readMessage()`, whichever Pest reaches first).

- [ ] **Step 3: Implement the 2 methods**

In `src/Drivers/AldinokemalV8Whatsapp.php`, insert these 2 methods right after the existing `updateMessage()` method (after its closing `}` on line 102) and before `request()` (starting line 104):

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

`Response` is already imported in this file — no new `use` statement needed.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Drivers/AldinokemalV8WhatsappTest.php -v`
Expected: PASS — full file green, including all pre-existing tests.

- [ ] **Step 5: Run full suite + static analysis**

Run: `composer test && vendor/bin/phpstan analyse --memory-limit=512M`
Expected: both green.

- [ ] **Step 6: Commit**

```bash
git add src/Drivers/AldinokemalV8Whatsapp.php tests/Drivers/AldinokemalV8WhatsappTest.php
git commit -m "feat(aldinokemal-v8): implement delete/read

deleteMessage() and readMessage() each POST directly to
/message/{message_id}/{delete,read} via the existing request()
client, no builder state or validateData() involved. Completes
coverage of the message tag's revoke/delete/reaction/update/read
endpoints except the out-of-scope star endpoint."
```

---

## Post-plan checklist (manual, not a task)

- Re-read `docs/superpowers/specs/2026-07-20-aldinokemal-v8-message-delete-read-design.md` "Out of scope" section and confirm `starMessage()` was not added anywhere.
- **CRITICAL for whoever executes this plan:** a prior implementer subagent on a task with this exact shape (direct-action method addition) completed the code correctly but never ran `git commit` and never wrote a fresh report, forcing the controller to intervene. Whoever dispatches Task 1/Task 2's implementer should explicitly instruct it to verify its own commit landed via `git log --oneline -1` before reporting DONE.
