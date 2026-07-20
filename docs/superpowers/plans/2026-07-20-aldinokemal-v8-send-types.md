# aldinokemal_v8 Send Message (Text/Image/File/Video) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `file()`/`video()` and optional send flags (`forwarded()`, `duration()`, `viewOnce()`, `compress()`, `gifPlayback()`) to `WhatsappInterface`, fully implemented for the `aldinokemal_v8` driver against `/send/video` and `/send/file`, with `/send/image` and `/send/message` extended to carry the same optional flags — while `aldinokemal` (v1) and `wuzapi` satisfy the interface via shared base-class defaults that throw `Exception('Not implemented')` for `file()`/`video()` and no-op for the optional flags.

**Architecture:** `Contracts\WhatsappInterface` grows 7 new fluent methods. `Drivers\Whatsapp` (abstract base, extended by all 3 drivers) gains the shared optional-flag state/setters plus throwing default `file()`/`video()` implementations, so `aldinokemal` and `wuzapi` need zero new code. `Drivers\AldinokemalV8Whatsapp` overrides `file()`/`video()` and extends `send()` to dispatch to `/send/video` → `/send/file` → `/send/image` → `/send/message` (first non-null property wins), reusing a new `resolveMediaUrl()` helper for the URL-or-storage-path resolution already used by `image()`.

**Tech Stack:** PHP 8.2, Laravel package (Illuminate HTTP client, `Illuminate\Support\Facades\Storage`), Pest 3 for tests (`Http::fake()` / `Http::assertSent()`), Orchestra Testbench.

## Global Constraints

- `declare(strict_types=1)` at the top of every modified/created PHP source file (already present in all touched files — do not remove).
- Driver classes are namespaced `FahriGunadi\Whatsapp\Drivers\*` (same casing as the rest of the package — `Whatsapp`, not `WhatsApp`; this was fixed as a prerequisite, see the `fix(whatsapp):` commit preceding Task 1).
- No `dd()`, `dump()`, or `ray()` anywhere in `src/` (enforced by `tests/ArchTest.php`).
- Laravel Pint runs on every commit via the `.git-hooks/pre-commit` hook (`pint --test`) — code must already be Pint-clean before committing; if the hook fails, run `composer format` and re-stage.
- New interface methods must be added to `src/Contracts/WhatsappInterface.php` with docblocks matching the existing style (one-line `@param`/`@return`-less summary + `@param` per parameter, no `@return` since return type is in the signature already, matching the file's current convention).
- Scope is exactly: `file()`, `video()`, `forwarded()`, `duration()`, `viewOnce()`, `compress()`, `gifPlayback()`. Do NOT add `mentions()`, binary/multipart upload support, or any `/send/*` endpoint beyond message/image/file/video (see spec's "Out of scope").

---

### Task 1: Interface contract + shared base-class defaults

**Files:**
- Modify: `src/Contracts/WhatsappInterface.php`
- Modify: `src/Drivers/Whatsapp.php`
- Test: `tests/Drivers/AldinokemalV8WhatsappTest.php` (new file — created here, extended by later tasks)

**Interfaces:**
- Produces: `WhatsappInterface::file(string $file): static`, `::video(string $video): static`, `::forwarded(bool $forwarded = true): static`, `::duration(int $seconds): static`, `::viewOnce(bool $viewOnce = true): static`, `::compress(bool $compress = true): static`, `::gifPlayback(bool $gifPlayback = true): static`. `Drivers\Whatsapp::$isForwarded` (bool), `::$duration` (?int), `::$viewOnce` (bool), `::$compress` (bool), `::$gifPlayback` (bool) — `protected` properties later tasks read directly from `AldinokemalV8Whatsapp` (which extends this class).

- [ ] **Step 1: Write the failing test**

Create `tests/Drivers/AldinokemalV8WhatsappTest.php`:

```php
<?php

use FahriGunadi\Whatsapp\Drivers\AldinokemalWhatsapp;
use FahriGunadi\Whatsapp\Drivers\WuzapiWhatsapp;

describe('driver base defaults', function () {
    it('throws not implemented for file() on aldinokemal v1', function () {
        (new AldinokemalWhatsapp)->file('https://example.com/a.pdf');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for video() on aldinokemal v1', function () {
        (new AldinokemalWhatsapp)->video('https://example.com/a.mp4');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for file() on wuzapi', function () {
        (new WuzapiWhatsapp)->file('https://example.com/a.pdf');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for video() on wuzapi', function () {
        (new WuzapiWhatsapp)->video('https://example.com/a.mp4');
    })->throws(Exception::class, 'Not implemented');

    it('stores optional flags fluently without affecting drivers that do not read them', function () {
        $driver = (new AldinokemalWhatsapp)
            ->forwarded()
            ->duration(86400)
            ->viewOnce()
            ->compress()
            ->gifPlayback();

        expect($driver)->toBeInstanceOf(AldinokemalWhatsapp::class);
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Drivers/AldinokemalV8WhatsappTest.php -v`
Expected: FAIL — `Call to undefined method FahriGunadi\Whatsapp\Drivers\AldinokemalWhatsapp::file()` (or `video()`/`forwarded()`/etc, whichever Pest hits first).

- [ ] **Step 3: Add the 7 methods to the interface**

In `src/Contracts/WhatsappInterface.php`, insert immediately after the existing `image()` method (after its closing `;` on line 41, right before the `/** Prepare the HTTP client request instance. */` docblock) and before the `request()` method:

```php
    /**
     * Set the file/document to be sent.
     *
     * @param  string  $file  The file URL or storage path.
     */
    public function file(string $file): static;

    /**
     * Set the video to be sent.
     *
     * @param  string  $video  The video URL or storage path.
     */
    public function video(string $video): static;

    /**
     * Mark the outgoing message as forwarded.
     *
     * @param  bool  $forwarded  Whether the message should be flagged as forwarded. Default true.
     */
    public function forwarded(bool $forwarded = true): static;

    /**
     * Set the disappearing message duration.
     *
     * @param  int  $seconds  Duration in seconds. Allowed values: 0 (no expiry), 86400 (24h), 604800 (7d), 7776000 (90d).
     */
    public function duration(int $seconds): static;

    /**
     * Mark the image/video to be sent as view-once.
     *
     * @param  bool  $viewOnce  Whether the media should be view-once. Default true.
     */
    public function viewOnce(bool $viewOnce = true): static;

    /**
     * Enable compression for the image/video to be sent.
     *
     * @param  bool  $compress  Whether the media should be compressed. Default true.
     */
    public function compress(bool $compress = true): static;

    /**
     * Display the video to be sent as a looping, silent, autoplay GIF.
     *
     * @param  bool  $gifPlayback  Whether the video should play back as a GIF. Default true.
     */
    public function gifPlayback(bool $gifPlayback = true): static;
```

- [ ] **Step 4: Add shared defaults to the abstract base class**

In `src/Drivers/Whatsapp.php`, add `use Exception;` to the imports (alongside the existing `use InvalidArgumentException;`), then add these properties and methods inside the `abstract class Whatsapp` body, right after the `use Logging;` line and before `formatPhone()`:

```php
    protected bool $isForwarded = false;

    protected ?int $duration = null;

    protected bool $viewOnce = false;

    protected bool $compress = false;

    protected bool $gifPlayback = false;

    /**
     * Set the file/document to be sent.
     *
     * Not implemented by default; only drivers whose backend supports
     * sending arbitrary files override this.
     *
     * @param  string  $file  The file URL or storage path.
     *
     * @throws Exception Always, unless overridden by a driver.
     */
    public function file(string $file): static
    {
        throw new Exception('Not implemented');
    }

    /**
     * Set the video to be sent.
     *
     * Not implemented by default; only drivers whose backend supports
     * sending video override this.
     *
     * @param  string  $video  The video URL or storage path.
     *
     * @throws Exception Always, unless overridden by a driver.
     */
    public function video(string $video): static
    {
        throw new Exception('Not implemented');
    }

    /**
     * Mark the outgoing message as forwarded.
     *
     * Stored unconditionally; only read by drivers whose send payload
     * supports an `is_forwarded` flag.
     *
     * @param  bool  $forwarded  Whether the message should be flagged as forwarded. Default true.
     */
    public function forwarded(bool $forwarded = true): static
    {
        $this->isForwarded = $forwarded;

        return $this;
    }

    /**
     * Set the disappearing message duration.
     *
     * Stored unconditionally; only read by drivers whose send payload
     * supports a `duration` field.
     *
     * @param  int  $seconds  Duration in seconds.
     */
    public function duration(int $seconds): static
    {
        $this->duration = $seconds;

        return $this;
    }

    /**
     * Mark the image/video to be sent as view-once.
     *
     * Stored unconditionally; only read by drivers whose send payload
     * supports a `view_once` field.
     *
     * @param  bool  $viewOnce  Whether the media should be view-once. Default true.
     */
    public function viewOnce(bool $viewOnce = true): static
    {
        $this->viewOnce = $viewOnce;

        return $this;
    }

    /**
     * Enable compression for the image/video to be sent.
     *
     * Stored unconditionally; only read by drivers whose send payload
     * supports a `compress` field.
     *
     * @param  bool  $compress  Whether the media should be compressed. Default true.
     */
    public function compress(bool $compress = true): static
    {
        $this->compress = $compress;

        return $this;
    }

    /**
     * Display the video to be sent as a looping, silent, autoplay GIF.
     *
     * Stored unconditionally; only read by drivers whose send payload
     * supports a `gif_playback` field.
     *
     * @param  bool  $gifPlayback  Whether the video should play back as a GIF. Default true.
     */
    public function gifPlayback(bool $gifPlayback = true): static
    {
        $this->gifPlayback = $gifPlayback;

        return $this;
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Drivers/AldinokemalV8WhatsappTest.php -v`
Expected: PASS — 5 tests, 5 assertions (or more).

- [ ] **Step 6: Run full suite + static analysis**

Run: `composer test && composer analyse`
Expected: both green. (`AldinokemalV8Whatsapp` does not yet implement `file()`/`video()` itself, but it inherits the base class's throwing defaults, so it still satisfies `WhatsappInterface` — no fatal errors.)

- [ ] **Step 7: Commit**

```bash
git add src/Contracts/WhatsappInterface.php src/Drivers/Whatsapp.php tests/Drivers/AldinokemalV8WhatsappTest.php
git commit -m "feat(whatsapp): add file/video/optional-flag contract

Add file(), video(), forwarded(), duration(), viewOnce(), compress(),
and gifPlayback() to WhatsappInterface. Default implementations live
on the abstract Whatsapp base class: file()/video() throw Not
implemented, the optional flags are stored but only read by drivers
whose send payload supports them. This lets aldinokemal (v1) and
wuzapi satisfy the interface without guessing at undocumented
endpoints."
```

---

### Task 2: `aldinokemal_v8` — `video()` + `/send/video`

**Files:**
- Modify: `src/Drivers/AldinokemalV8Whatsapp.php`
- Test: `tests/Drivers/AldinokemalV8WhatsappTest.php`

**Interfaces:**
- Consumes: `Drivers\Whatsapp::$isForwarded`, `::$duration`, `::$viewOnce`, `::$compress`, `::$gifPlayback` (protected, from Task 1).
- Produces: `AldinokemalV8Whatsapp::video(string $video): static` (overrides base). `AldinokemalV8Whatsapp::resolveMediaUrl(string $path): string` (private) — later tasks (3, 4) reuse this instead of re-inlining the `isUrl()`/`Storage::url()` check.

- [ ] **Step 1: Write the failing test**

Append to `tests/Drivers/AldinokemalV8WhatsappTest.php` (after the closing `});` of the `describe('driver base defaults', ...)` block):

```php
use FahriGunadi\Whatsapp\Drivers\AldinokemalV8Whatsapp;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'whatsapp.username' => 'user',
        'whatsapp.password' => 'pass',
        'whatsapp.base_url' => 'https://gowa.example.com',
    ]);
});

describe('AldinokemalV8Whatsapp::send() video', function () {
    it('posts to /send/video with an absolute video url', function () {
        Http::fake(['*' => Http::response(['status' => 200], 200)]);

        (new AldinokemalV8Whatsapp)
            ->to('6289685028129@s.whatsapp.net')
            ->video('https://example.com/sample.mp4')
            ->message('a caption')
            ->replyMessage('3EB089B9D6ADD58153C561')
            ->viewOnce()
            ->compress()
            ->gifPlayback()
            ->duration(86400)
            ->forwarded()
            ->send();

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://gowa.example.com/send/video'
                && $request['phone'] === '6289685028129@s.whatsapp.net'
                && $request['caption'] === 'a caption'
                && $request['reply_message_id'] === '3EB089B9D6ADD58153C561'
                && $request['view_once'] === true
                && $request['video_url'] === 'https://example.com/sample.mp4'
                && $request['compress'] === true
                && $request['gif_playback'] === true
                && $request['duration'] === 86400
                && $request['is_forwarded'] === true;
        });
    });

    it('allows sending a video without a caption', function () {
        Http::fake(['*' => Http::response(['status' => 200], 200)]);

        (new AldinokemalV8Whatsapp)
            ->to('6289685028129@s.whatsapp.net')
            ->video('https://example.com/sample.mp4')
            ->send();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://gowa.example.com/send/video');
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Drivers/AldinokemalV8WhatsappTest.php -v`
Expected: FAIL — `Call to undefined method FahriGunadi\Whatsapp\Drivers\AldinokemalV8Whatsapp::video()` is inherited from base and throws `Exception('Not implemented')`, so the test fails with that exception instead (uncaught `Exception: Not implemented`).

- [ ] **Step 3: Implement `video()`, `resolveMediaUrl()`, and the `/send/video` dispatch**

In `src/Drivers/AldinokemalV8Whatsapp.php`:

Add a new private property alongside the existing `private ?string $image = null;`:

```php
    private ?string $video = null;
```

Add the `video()` method right after the existing `image()` method:

```php
    public function video(string $video): static
    {
        $this->video = $video;

        return $this;
    }
```

Replace the `send()` method body's `image_url` line and add the `/send/video` branch — replace the whole `send()` method with:

```php
    public function send(): Response
    {
        $this->validateData();

        if ($this->video) {
            return $this->request()->post('/send/video', [
                'phone' => $this->to,
                'caption' => $this->message,
                'reply_message_id' => $this->replyMessageId,
                'view_once' => $this->viewOnce,
                'video_url' => $this->resolveMediaUrl($this->video),
                'compress' => $this->compress,
                'gif_playback' => $this->gifPlayback,
                'duration' => $this->duration,
                'is_forwarded' => $this->isForwarded,
            ]);
        }

        if ($this->image) {
            return $this->request()->post('/send/image', [
                'phone' => $this->to,
                'caption' => $this->message,
                'reply_message_id' => $this->replyMessageId,
                'image_url' => $this->resolveMediaUrl($this->image),
            ]);
        }

        return $this->request()->post('/send/message', [
            'phone' => $this->to,
            'reply_message_id' => $this->replyMessageId,
            'message' => $this->message,
        ]);
    }

    private function resolveMediaUrl(string $path): string
    {
        return str($path)->isUrl() ? $path : Storage::url($path);
    }
```

Update `validateData()` to accept a video-only message:

```php
    protected function validateData()
    {
        throw_unless($this->to, new Exception('Target must be set'));

        throw_if(! $this->message && ! $this->image && ! $this->video, new Exception('Message or Image must be set'));
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Drivers/AldinokemalV8WhatsappTest.php -v`
Expected: PASS — all tests including Task 1's.

- [ ] **Step 5: Run full suite + static analysis**

Run: `composer test && composer analyse`
Expected: both green.

- [ ] **Step 6: Commit**

```bash
git add src/Drivers/AldinokemalV8Whatsapp.php tests/Drivers/AldinokemalV8WhatsappTest.php
git commit -m "feat(aldinokemal-v8): send video via /send/video

Add video(), a resolveMediaUrl() helper shared with image(), and
dispatch send() to /send/video (with view_once/compress/
gif_playback/duration/is_forwarded) when a video is set."
```

---

### Task 3: `aldinokemal_v8` — `file()` + `/send/file`

**Files:**
- Modify: `src/Drivers/AldinokemalV8Whatsapp.php`
- Test: `tests/Drivers/AldinokemalV8WhatsappTest.php`

**Interfaces:**
- Consumes: `AldinokemalV8Whatsapp::resolveMediaUrl(string $path): string` (private, from Task 2).
- Produces: `AldinokemalV8Whatsapp::file(string $file): static` (overrides base).

- [ ] **Step 1: Write the failing test**

Append to `tests/Drivers/AldinokemalV8WhatsappTest.php`:

```php
describe('AldinokemalV8Whatsapp::send() file', function () {
    it('posts to /send/file with an absolute file url', function () {
        Http::fake(['*' => Http::response(['status' => 200], 200)]);

        (new AldinokemalV8Whatsapp)
            ->to('6289685028129@s.whatsapp.net')
            ->file('https://example.com/document.pdf')
            ->message('a caption')
            ->replyMessage('3EB089B9D6ADD58153C561')
            ->duration(604800)
            ->forwarded()
            ->send();

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://gowa.example.com/send/file'
                && $request['phone'] === '6289685028129@s.whatsapp.net'
                && $request['caption'] === 'a caption'
                && $request['reply_message_id'] === '3EB089B9D6ADD58153C561'
                && $request['file_url'] === 'https://example.com/document.pdf'
                && $request['duration'] === 604800
                && $request['is_forwarded'] === true;
        });
    });

    it('prefers video over file when both are set', function () {
        Http::fake(['*' => Http::response(['status' => 200], 200)]);

        (new AldinokemalV8Whatsapp)
            ->to('6289685028129@s.whatsapp.net')
            ->file('https://example.com/document.pdf')
            ->video('https://example.com/sample.mp4')
            ->send();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://gowa.example.com/send/video');
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Drivers/AldinokemalV8WhatsappTest.php -v`
Expected: FAIL — uncaught `Exception: Not implemented` from the inherited base `file()`.

- [ ] **Step 3: Implement `file()` and the `/send/file` dispatch**

In `src/Drivers/AldinokemalV8Whatsapp.php`, add a new private property next to `$video`:

```php
    private ?string $file = null;
```

Add the `file()` method after `video()`:

```php
    public function file(string $file): static
    {
        $this->file = $file;

        return $this;
    }
```

In `send()`, insert a `/send/file` branch between the `video` branch and the `image` branch:

```php
        if ($this->file) {
            return $this->request()->post('/send/file', [
                'phone' => $this->to,
                'caption' => $this->message,
                'reply_message_id' => $this->replyMessageId,
                'file_url' => $this->resolveMediaUrl($this->file),
                'is_forwarded' => $this->isForwarded,
                'duration' => $this->duration,
            ]);
        }
```

(Full `send()` order after this change: `video` → `file` → `image` → plain `message`.)

Update `validateData()`:

```php
    protected function validateData()
    {
        throw_unless($this->to, new Exception('Target must be set'));

        throw_if(! $this->message && ! $this->image && ! $this->video && ! $this->file, new Exception('Message or Image must be set'));
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Drivers/AldinokemalV8WhatsappTest.php -v`
Expected: PASS.

- [ ] **Step 5: Run full suite + static analysis**

Run: `composer test && composer analyse`
Expected: both green.

- [ ] **Step 6: Commit**

```bash
git add src/Drivers/AldinokemalV8Whatsapp.php tests/Drivers/AldinokemalV8WhatsappTest.php
git commit -m "feat(aldinokemal-v8): send file via /send/file

Add file() and dispatch send() to /send/file (with is_forwarded/
duration) when a file is set. Dispatch priority is video > file >
image > text."
```

---

### Task 4: `aldinokemal_v8` — extend `/send/image` with optional flags

**Files:**
- Modify: `src/Drivers/AldinokemalV8Whatsapp.php`
- Test: `tests/Drivers/AldinokemalV8WhatsappTest.php`

**Interfaces:**
- Consumes: `Drivers\Whatsapp::$viewOnce`, `::$compress`, `::$duration`, `::$isForwarded` (from Task 1).

- [ ] **Step 1: Write the failing test**

Append to `tests/Drivers/AldinokemalV8WhatsappTest.php`:

```php
describe('AldinokemalV8Whatsapp::send() image', function () {
    it('posts to /send/image with the optional flags', function () {
        Http::fake(['*' => Http::response(['status' => 200], 200)]);

        (new AldinokemalV8Whatsapp)
            ->to('6289685028129@s.whatsapp.net')
            ->image('https://example.com/photo.jpg')
            ->message('a caption')
            ->viewOnce()
            ->compress()
            ->duration(7776000)
            ->forwarded()
            ->send();

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://gowa.example.com/send/image'
                && $request['image_url'] === 'https://example.com/photo.jpg'
                && $request['view_once'] === true
                && $request['compress'] === true
                && $request['duration'] === 7776000
                && $request['is_forwarded'] === true;
        });
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Drivers/AldinokemalV8WhatsappTest.php -v`
Expected: FAIL — `Undefined array key "view_once"` (the current `/send/image` payload doesn't include it).

- [ ] **Step 3: Extend the `/send/image` payload**

In `src/Drivers/AldinokemalV8Whatsapp.php`, replace the `/send/image` branch inside `send()` with:

```php
        if ($this->image) {
            return $this->request()->post('/send/image', [
                'phone' => $this->to,
                'caption' => $this->message,
                'reply_message_id' => $this->replyMessageId,
                'view_once' => $this->viewOnce,
                'image_url' => $this->resolveMediaUrl($this->image),
                'compress' => $this->compress,
                'duration' => $this->duration,
                'is_forwarded' => $this->isForwarded,
            ]);
        }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Drivers/AldinokemalV8WhatsappTest.php -v`
Expected: PASS.

- [ ] **Step 5: Run full suite + static analysis**

Run: `composer test && composer analyse`
Expected: both green.

- [ ] **Step 6: Commit**

```bash
git add src/Drivers/AldinokemalV8Whatsapp.php tests/Drivers/AldinokemalV8WhatsappTest.php
git commit -m "feat(aldinokemal-v8): add optional flags to /send/image payload

view_once, compress, duration, and is_forwarded now match the
/send/video and /send/file payloads, per openapi.yaml."
```

---

### Task 5: `aldinokemal_v8` — extend `/send/message` with optional flags

**Files:**
- Modify: `src/Drivers/AldinokemalV8Whatsapp.php`
- Test: `tests/Drivers/AldinokemalV8WhatsappTest.php`

**Interfaces:**
- Consumes: `Drivers\Whatsapp::$duration`, `::$isForwarded` (from Task 1).

- [ ] **Step 1: Write the failing test**

Append to `tests/Drivers/AldinokemalV8WhatsappTest.php`:

```php
describe('AldinokemalV8Whatsapp::send() text', function () {
    it('posts to /send/message with the optional flags', function () {
        Http::fake(['*' => Http::response(['status' => 200], 200)]);

        (new AldinokemalV8Whatsapp)
            ->to('6289685028129@s.whatsapp.net')
            ->message('selamat malam')
            ->duration(86400)
            ->forwarded()
            ->send();

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://gowa.example.com/send/message'
                && $request['message'] === 'selamat malam'
                && $request['duration'] === 86400
                && $request['is_forwarded'] === true;
        });
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Drivers/AldinokemalV8WhatsappTest.php -v`
Expected: FAIL — `Undefined array key "duration"`.

- [ ] **Step 3: Extend the `/send/message` payload**

In `src/Drivers/AldinokemalV8Whatsapp.php`, replace the final `return` in `send()` (the plain-text branch) with:

```php
        return $this->request()->post('/send/message', [
            'phone' => $this->to,
            'reply_message_id' => $this->replyMessageId,
            'message' => $this->message,
            'is_forwarded' => $this->isForwarded,
            'duration' => $this->duration,
        ]);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Drivers/AldinokemalV8WhatsappTest.php -v`
Expected: PASS — full file green.

- [ ] **Step 5: Run full suite + static analysis**

Run: `composer test && composer analyse`
Expected: both green.

- [ ] **Step 6: Final full-repo check**

Run: `composer test-coverage`
Expected: passes; note the coverage delta for `AldinokemalV8Whatsapp` (informational only — no coverage threshold is enforced in this repo).

- [ ] **Step 7: Commit**

```bash
git add src/Drivers/AldinokemalV8Whatsapp.php tests/Drivers/AldinokemalV8WhatsappTest.php
git commit -m "feat(aldinokemal-v8): add optional flags to /send/message payload

is_forwarded and duration now match the other three send payloads,
completing optional-flag parity across text/image/file/video."
```

---

## Post-plan checklist (manual, not a task)

- Re-read `docs/superpowers/specs/2026-07-20-aldinokemal-v8-send-types-design.md` "Out of scope" section and confirm nothing there leaked in.
- `README.md`'s `## Usage` section only shows `image()`/`message()` examples — consider (in a follow-up, not this plan) adding a `file()`/`video()` usage snippet once this ships.
