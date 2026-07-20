# aldinokemal_v8 Message Star/Unstar/Forward/Download Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `starMessage()`, `unstarMessage()`, `forwardMessage()`, `downloadMessage()` to `WhatsappInterface` as direct-action methods, fully implemented for the `aldinokemal_v8` driver against `/message/{message_id}/{star,unstar,forward,download}`, completing the entire `message` API tag (9/9 endpoints across this and two prior features), while `aldinokemal` (v1) and `wuzapi` satisfy the interface via the abstract base class's throwing default.

**Architecture:** Identical to the two already-merged `message`-tag features this one completes. `Contracts\WhatsappInterface` grows 4 new methods. `Drivers\Whatsapp` (abstract base) gets 4 more throwing defaults. `Drivers\AldinokemalV8Whatsapp` overrides all four — three as direct `POST`s via `$this->request()`, one (`downloadMessage()`) as a `GET` with `phone` as a query parameter instead of a JSON body, the only method in this family shaped that way.

**Tech Stack:** PHP 8.2, Laravel package (Illuminate HTTP client), Pest 3 for tests (`Http::fake()` / `Http::assertSent()`), Orchestra Testbench.

## Global Constraints

- `declare(strict_types=1)` at the top of every modified file (already present — do not remove).
- Namespace `FahriGunadi\Whatsapp\Drivers\*` (lowercase `Whatsapp`) — do not change.
- No `dd()`, `dump()`, or `ray()` anywhere in `src/` (enforced by `tests/ArchTest.php`).
- Laravel Pint runs on every commit via the `.git-hooks/pre-commit` hook (`pint --test`) — code must already be Pint-clean before committing.
- `Illuminate\Http\Client\Response` is already imported in all three files this plan touches — no new `use` statement needed anywhere.
- `forwardMessage()`'s `$duration` and `$forceReupload` are plain method parameters — do NOT wire them to the fluent builder's `Drivers\Whatsapp::$duration` property (that property belongs to the unrelated `to()->message()->send()` call path).
- `downloadMessage()` issues a `GET`, not a `POST`, with `phone` as a query parameter (second array argument to `PendingRequest::get()`), not a JSON body field. It returns the raw `Response` — do not parse the JSON or extract `file_path`/`file_url` in the driver.
- Scope is exactly: `starMessage()`, `unstarMessage()`, `forwardMessage()`, `downloadMessage()`. This completes the `message` tag — there is nothing left in it after this plan.
- PHPStan's default memory limit (128M) is too low in this environment; always run `vendor/bin/phpstan analyse --memory-limit=512M` directly rather than bare `composer analyse`.

---

### Task 1: Interface contract + shared base-class throwing defaults

**Files:**
- Modify: `src/Contracts/WhatsappInterface.php`
- Modify: `src/Drivers/Whatsapp.php`
- Modify: `tests/Drivers/AldinokemalV8WhatsappTest.php`

**Interfaces:**
- Produces: `WhatsappInterface::starMessage(string $messageId, string $phone): Response`, `::unstarMessage(string $messageId, string $phone): Response`, `::forwardMessage(string $messageId, string $phone, ?int $duration = null, bool $forceReupload = false): Response`, `::downloadMessage(string $messageId, string $phone): Response`. All four throw `Exception('Not implemented')` by default via `Drivers\Whatsapp`, inherited as-is by `AldinokemalWhatsapp` and `WuzapiWhatsapp`.

- [ ] **Step 1: Write the failing test**

In `tests/Drivers/AldinokemalV8WhatsappTest.php`, inside the existing `describe('driver base defaults', function () { ... });` block, insert these 8 `it(...)` calls right after the existing `it('throws not implemented for readMessage() on wuzapi', ...)` block (currently ending at line 61) and before the `it('stores optional flags fluently ...')` block (currently starting at line 63):

```php
    it('throws not implemented for starMessage() on aldinokemal v1', function () {
        (new AldinokemalWhatsapp)->starMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for unstarMessage() on aldinokemal v1', function () {
        (new AldinokemalWhatsapp)->unstarMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for forwardMessage() on aldinokemal v1', function () {
        (new AldinokemalWhatsapp)->forwardMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for downloadMessage() on aldinokemal v1', function () {
        (new AldinokemalWhatsapp)->downloadMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for starMessage() on wuzapi', function () {
        (new WuzapiWhatsapp)->starMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for unstarMessage() on wuzapi', function () {
        (new WuzapiWhatsapp)->unstarMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for forwardMessage() on wuzapi', function () {
        (new WuzapiWhatsapp)->forwardMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for downloadMessage() on wuzapi', function () {
        (new WuzapiWhatsapp)->downloadMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');
    })->throws(Exception::class, 'Not implemented');
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Drivers/AldinokemalV8WhatsappTest.php -v`
Expected: FAIL — `Call to undefined method FahriGunadi\Whatsapp\Drivers\AldinokemalWhatsapp::starMessage()` (or whichever of the 8 new assertions Pest reaches first).

- [ ] **Step 3: Add the 4 methods to the interface**

In `src/Contracts/WhatsappInterface.php`, insert immediately after the existing `readMessage()` method (after its closing `;` on line 137) and before the `request()` method (docblock starting line 139):

```php
    /**
     * Star (bookmark) a message.
     *
     * @param  string  $messageId  The ID of the message to star.
     * @param  string  $phone  The chat (phone/group JID) the message was sent in.
     */
    public function starMessage(string $messageId, string $phone): Response;

    /**
     * Unstar (remove bookmark from) a message.
     *
     * @param  string  $messageId  The ID of the message to unstar.
     * @param  string  $phone  The chat (phone/group JID) the message was sent in.
     */
    public function unstarMessage(string $messageId, string $phone): Response;

    /**
     * Forward a message from local chat storage to another chat.
     *
     * @param  string  $messageId  The ID of the message to forward.
     * @param  string  $phone  The destination phone number or group JID.
     * @param  int|null  $duration  Optional disappearing message duration override in seconds (0, 86400, 604800, 7776000).
     * @param  bool  $forceReupload  Skip media reference reuse and re-upload media before sending. Default false.
     */
    public function forwardMessage(string $messageId, string $phone, ?int $duration = null, bool $forceReupload = false): Response;

    /**
     * Retrieve the API's saved-location info for a message's downloaded media.
     *
     * Returns the raw response describing where the media was saved
     * (file_path/file_url/file_size); it does not fetch the binary itself.
     *
     * @param  string  $messageId  The ID of the message whose media to look up.
     * @param  string  $phone  The chat (phone/group JID) the message was sent in.
     */
    public function downloadMessage(string $messageId, string $phone): Response;
```

- [ ] **Step 4: Add throwing defaults to the abstract base class**

In `src/Drivers/Whatsapp.php`, insert these 4 methods right after the existing `readMessage()` method (after its closing `}` on line 212) and before the `formatPhone()` method's docblock (starting line 214):

```php
    /**
     * Star (bookmark) a message.
     *
     * Not implemented by default; only drivers whose backend supports
     * starring messages override this.
     *
     * @param  string  $messageId  The ID of the message to star.
     * @param  string  $phone  The chat (phone/group JID) the message was sent in.
     *
     * @throws Exception Always, unless overridden by a driver.
     */
    public function starMessage(string $messageId, string $phone): Response
    {
        throw new Exception('Not implemented');
    }

    /**
     * Unstar (remove bookmark from) a message.
     *
     * Not implemented by default; only drivers whose backend supports
     * unstarring messages override this.
     *
     * @param  string  $messageId  The ID of the message to unstar.
     * @param  string  $phone  The chat (phone/group JID) the message was sent in.
     *
     * @throws Exception Always, unless overridden by a driver.
     */
    public function unstarMessage(string $messageId, string $phone): Response
    {
        throw new Exception('Not implemented');
    }

    /**
     * Forward a message from local chat storage to another chat.
     *
     * Not implemented by default; only drivers whose backend supports
     * message forwarding override this.
     *
     * @param  string  $messageId  The ID of the message to forward.
     * @param  string  $phone  The destination phone number or group JID.
     * @param  int|null  $duration  Optional disappearing message duration override in seconds.
     * @param  bool  $forceReupload  Skip media reference reuse and re-upload media before sending. Default false.
     *
     * @throws Exception Always, unless overridden by a driver.
     */
    public function forwardMessage(string $messageId, string $phone, ?int $duration = null, bool $forceReupload = false): Response
    {
        throw new Exception('Not implemented');
    }

    /**
     * Retrieve the API's saved-location info for a message's downloaded media.
     *
     * Not implemented by default; only drivers whose backend supports
     * media download override this.
     *
     * @param  string  $messageId  The ID of the message whose media to look up.
     * @param  string  $phone  The chat (phone/group JID) the message was sent in.
     *
     * @throws Exception Always, unless overridden by a driver.
     */
    public function downloadMessage(string $messageId, string $phone): Response
    {
        throw new Exception('Not implemented');
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Drivers/AldinokemalV8WhatsappTest.php -v`
Expected: PASS — all tests including the pre-existing ones.

- [ ] **Step 6: Run full suite + static analysis**

Run: `composer test && vendor/bin/phpstan analyse --memory-limit=512M`
Expected: both green.

- [ ] **Step 7: Commit**

```bash
git add src/Contracts/WhatsappInterface.php src/Drivers/Whatsapp.php tests/Drivers/AldinokemalV8WhatsappTest.php
git commit -m "feat(whatsapp): add star/unstar/forward/download contract

Add starMessage(), unstarMessage(), forwardMessage(), and
downloadMessage() to WhatsappInterface, completing the message tag's
9 endpoints across this and two prior features. Default
implementations on the abstract Whatsapp base class throw Not
implemented, mirroring the rest of the message-manipulation methods,
so aldinokemal (v1) and wuzapi satisfy the interface without new
code."
```

---

### Task 2: `aldinokemal_v8` — implement star/unstar/forward/download

**Files:**
- Modify: `src/Drivers/AldinokemalV8Whatsapp.php`
- Modify: `tests/Drivers/AldinokemalV8WhatsappTest.php`

**Interfaces:**
- Consumes: `$this->request(): PendingRequest` (existing method, unchanged).
- Produces: `AldinokemalV8Whatsapp::starMessage(...)`, `::unstarMessage(...)`, `::forwardMessage(...)`, `::downloadMessage(...)` (all override the base class's throwing defaults from Task 1).

- [ ] **Step 1: Write the failing tests**

In `tests/Drivers/AldinokemalV8WhatsappTest.php`, add these 4 `it(...)` blocks inside the existing `describe('AldinokemalV8Whatsapp message manipulation', function () { ... });` block — insert them right after the `it('posts to /message/{message_id}/read with the phone', ...)` block (currently ending at line 284) and before the closing `});` of the `describe` (currently line 285):

```php
    it('posts to /message/{message_id}/star with the phone', function () {
        Http::fake(['*' => Http::response(['status' => 200], 200)]);

        (new AldinokemalV8Whatsapp)->starMessage(
            '3EB089B9D6ADD58153C561',
            '6289685028129@s.whatsapp.net'
        );

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://gowa.example.com/message/3EB089B9D6ADD58153C561/star'
                && $request['phone'] === '6289685028129@s.whatsapp.net';
        });
    });

    it('posts to /message/{message_id}/unstar with the phone', function () {
        Http::fake(['*' => Http::response(['status' => 200], 200)]);

        (new AldinokemalV8Whatsapp)->unstarMessage(
            '3EB089B9D6ADD58153C561',
            '6289685028129@s.whatsapp.net'
        );

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://gowa.example.com/message/3EB089B9D6ADD58153C561/unstar'
                && $request['phone'] === '6289685028129@s.whatsapp.net';
        });
    });

    it('posts to /message/{message_id}/forward with the phone and optional fields', function () {
        Http::fake(['*' => Http::response(['status' => 200], 200)]);

        (new AldinokemalV8Whatsapp)->forwardMessage(
            '3EB089B9D6ADD58153C561',
            '6289685028129@s.whatsapp.net',
            86400,
            true
        );

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://gowa.example.com/message/3EB089B9D6ADD58153C561/forward'
                && $request['phone'] === '6289685028129@s.whatsapp.net'
                && $request['duration'] === 86400
                && $request['force_reupload'] === true;
        });
    });

    it('gets /message/{message_id}/download with the phone as a query parameter', function () {
        Http::fake(['*' => Http::response(['status' => 200], 200)]);

        (new AldinokemalV8Whatsapp)->downloadMessage(
            '3EB089B9D6ADD58153C561',
            '6289685028129@s.whatsapp.net'
        );

        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET'
                && str($request->url())->startsWith('https://gowa.example.com/message/3EB089B9D6ADD58153C561/download?')
                && str($request->url())->contains(rawurlencode('6289685028129@s.whatsapp.net'));
        });
    });
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Drivers/AldinokemalV8WhatsappTest.php -v`
Expected: FAIL — uncaught `Exception: Not implemented` from the inherited base `starMessage()` (or whichever of the 4 methods Pest reaches first).

- [ ] **Step 3: Implement the 4 methods**

In `src/Drivers/AldinokemalV8Whatsapp.php`, insert these 4 methods right after the existing `readMessage()` method (after its closing `}` on line 116) and before `request()` (starting line 118):

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
git commit -m "feat(aldinokemal-v8): implement star/unstar/forward/download

starMessage(), unstarMessage(), and forwardMessage() POST directly
to their /message/{message_id}/* endpoints via the existing
request() client. downloadMessage() is the one GET in the family,
with phone as a query parameter instead of a JSON body. Completes
the message tag's 9/9 endpoint coverage for this driver."
```

---

## Post-plan checklist (manual, not a task)

- Re-read `docs/superpowers/specs/2026-07-20-aldinokemal-v8-message-star-forward-download-design.md` "Out of scope" section and confirm no binary-download-fetching logic was added anywhere.
- **For whoever executes this plan:** verify the implementer's `git commit` actually landed via `git log --oneline -1` before accepting a DONE report — a prior task's implementer skipped this and had to be caught by the controller.
