# WhatsappFake (Outbound Send + Message Manipulation) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `WhatsappFake` testing double (`FahriGunadi\Whatsapp\Testing\WhatsappFake`) implementing the full `WhatsappInterface`, with real record-and-assert behavior for the outbound send builder (7 methods) and the 9 message-manipulation methods (16 of 41 interface methods total), and a `Whatsapp::fake()` facade method that swaps it into the container — mirroring Laravel's `Mail::fake()`/`Queue::fake()`/`Notification::fake()` pattern.

**Architecture:** `WhatsappFake extends Drivers\Whatsapp implements WhatsappInterface`, built up in 4 stages: (1) a full skeleton satisfying the interface with `Exception('Not implemented')` everywhere it must declare a method itself, (2) real outbound-builder + `send()` behavior with `assertSent`/`assertNotSent`/`assertSentCount`/`assertNothingSent`, (3) real message-manipulation behavior with a generic `assertCalled`/`assertNotCalled`/`assertCalledCount` (avoiding 9 near-duplicate named assertions), (4) `Facades\Whatsapp::fake()` wiring via Laravel's `Facade::swap()`.

**Tech Stack:** PHP 8.2, Laravel package (Illuminate HTTP client's `Http::response()` factory for zero-network fake responses), `PHPUnit\Framework\Assert` for assertion primitives (same technique Laravel core's own `Illuminate\Support\Testing\Fakes\*` classes use), Pest 3 for this plan's own tests, Orchestra Testbench.

## Global Constraints

- `declare(strict_types=1)` at the top of every new PHP file.
- New namespace `FahriGunadi\Whatsapp\Testing\*` maps to `src/Testing/` automatically under the existing PSR-4 rule (`FahriGunadi\Whatsapp\` → `src/`) — no `composer.json` change needed.
- No `dd()`, `dump()`, or `ray()` anywhere in `src/` (enforced by `tests/ArchTest.php`).
- Laravel Pint runs on every commit via `.git-hooks/pre-commit` (`pint --test`) — code must be Pint-clean before committing; run `composer format` if it fails.
- `vendor/bin/phpstan analyse --memory-limit=512M` (not bare `composer analyse`) — this repo's default 128M PHPStan memory limit is too low in this environment.
- Referencing `PHPUnit\Framework\Assert` from inside `src/Testing/WhatsappFake.php` is intentional and correct, not a stray production dependency — `phpunit`/`pest` are `require-dev` only in `composer.json`, and PHP only autoloads a referenced class when a method that touches it actually executes. `WhatsappFake`'s assertion methods only ever run inside a consuming app's own test suite, which will already have a PHPUnit-based test runner installed (Pest included). This is the exact same technique Laravel core's `Illuminate\Support\Testing\Fakes\*` classes use.
- Scope is exactly 16 methods get real fake behavior in this plan: outbound builder `to`, `replyMessage`, `message`, `image`, `file`, `video`, `send` (7 — `forwarded`/`duration`/`viewOnce`/`compress`/`gifPlayback` are inherited unchanged from `Drivers\Whatsapp`, already fake-safe, no code needed) and message manipulation `revokeMessage`, `reactMessage`, `updateMessage`, `deleteMessage`, `readMessage`, `starMessage`, `unstarMessage`, `forwardMessage`, `downloadMessage` (9). The remaining 15 methods (13 `webhook*()`, `getMyGroups()`, `request()`) must exist (PHP cannot partially implement an interface) but throw `Exception('Not implemented')` and are NOT built out further in this plan — `request()` in particular is expected to stay throwing even in future phases, since none of `WhatsappFake`'s own methods ever call it; the 13 webhook methods and `getMyGroups()` are genuinely deferred-for-now, to be built out in a later phase.
- `Whatsapp::fake()` goes on `src/Facades/Whatsapp.php` (the facade registered as the `Whatsapp` alias in `composer.json`, fixed in commit `a2fb031` to correctly resolve `WhatsappInterface`), NOT on `src/Whatsapp.php` (the older, second facade class kept only for the README's direct-class-reference usage).

---

### Task 1: `WhatsappFake` skeleton — full interface satisfaction, everything throws

**Files:**
- Create: `src/Testing/WhatsappFake.php`
- Create: `tests/Testing/WhatsappFakeTest.php`

**Interfaces:**
- Produces: `FahriGunadi\Whatsapp\Testing\WhatsappFake` — a concrete class satisfying `WhatsappInterface` in full. Later tasks in this plan modify this same file to replace specific stub method bodies with real behavior; they do not create new files.

- [ ] **Step 1: Write the failing test**

Create `tests/Testing/WhatsappFakeTest.php`:

```php
<?php

use FahriGunadi\Whatsapp\Contracts\WhatsappInterface;
use FahriGunadi\Whatsapp\Testing\WhatsappFake;

it('implements WhatsappInterface', function () {
    expect(new WhatsappFake)->toBeInstanceOf(WhatsappInterface::class);
});

it('throws not implemented for methods not yet faked', function (string $method, array $arguments) {
    $fake = new WhatsappFake;

    $fake->{$method}(...$arguments);
})->throws(Exception::class, 'Not implemented')->with([
    'to' => ['to', ['628123456789']],
    'replyMessage' => ['replyMessage', ['3EB089B9D6ADD58153C561']],
    'message' => ['message', ['hello']],
    'image' => ['image', ['https://example.com/a.jpg']],
    'request' => ['request', []],
    'send' => ['send', []],
    'webhookSender' => ['webhookSender', []],
    'webhookChat' => ['webhookChat', []],
    'webhookMessageText' => ['webhookMessageText', []],
    'webhookMessageId' => ['webhookMessageId', []],
    'webhookMessageTimestamp' => ['webhookMessageTimestamp', []],
    'webhookPushname' => ['webhookPushname', []],
    'webhookIsGroup' => ['webhookIsGroup', []],
    'webhookIsImage' => ['webhookIsImage', []],
    'webhookImageMimeType' => ['webhookImageMimeType', []],
    'webhookImage' => ['webhookImage', []],
    'webhookIsDocument' => ['webhookIsDocument', []],
    'webhookDocumentMimeType' => ['webhookDocumentMimeType', []],
    'webhookDocument' => ['webhookDocument', []],
    'getMyGroups' => ['getMyGroups', []],
]);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Testing/WhatsappFakeTest.php -v`
Expected: FAIL — `Class "FahriGunadi\Whatsapp\Testing\WhatsappFake" not found`.

- [ ] **Step 3: Create the skeleton**

Create `src/Testing/WhatsappFake.php`:

```php
<?php

declare(strict_types=1);

namespace FahriGunadi\Whatsapp\Testing;

use Exception;
use FahriGunadi\Whatsapp\Contracts\WhatsappInterface;
use FahriGunadi\Whatsapp\Drivers\Whatsapp;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;

class WhatsappFake extends Whatsapp implements WhatsappInterface
{
    public function to(string $phone): static
    {
        throw new Exception('Not implemented');
    }

    public function replyMessage(string $messageId, ?string $participant = null): static
    {
        throw new Exception('Not implemented');
    }

    public function message(string $message): static
    {
        throw new Exception('Not implemented');
    }

    public function image(string $image): static
    {
        throw new Exception('Not implemented');
    }

    public function request(): PendingRequest
    {
        throw new Exception('Not implemented');
    }

    public function send(): Response
    {
        throw new Exception('Not implemented');
    }

    public function webhookSender(): string
    {
        throw new Exception('Not implemented');
    }

    public function webhookChat(): string
    {
        throw new Exception('Not implemented');
    }

    public function webhookMessageText(): ?string
    {
        throw new Exception('Not implemented');
    }

    public function webhookMessageId(): ?string
    {
        throw new Exception('Not implemented');
    }

    public function webhookMessageTimestamp(): ?string
    {
        throw new Exception('Not implemented');
    }

    public function webhookPushname(): ?string
    {
        throw new Exception('Not implemented');
    }

    public function webhookIsGroup(): bool
    {
        throw new Exception('Not implemented');
    }

    public function webhookIsImage(): bool
    {
        throw new Exception('Not implemented');
    }

    public function webhookImageMimeType(): ?string
    {
        throw new Exception('Not implemented');
    }

    public function webhookImage(): ?string
    {
        throw new Exception('Not implemented');
    }

    public function webhookIsDocument(): bool
    {
        throw new Exception('Not implemented');
    }

    public function webhookDocumentMimeType(): ?string
    {
        throw new Exception('Not implemented');
    }

    public function webhookDocument(): ?string
    {
        throw new Exception('Not implemented');
    }

    public function getMyGroups(): Collection
    {
        throw new Exception('Not implemented');
    }
}
```

Note: `file()`, `video()`, `forwarded()`, `duration()`, `viewOnce()`, `compress()`, `gifPlayback()`, `revokeMessage()`, `reactMessage()`, `updateMessage()`, `deleteMessage()`, `readMessage()`, `starMessage()`, `unstarMessage()`, `forwardMessage()`, `downloadMessage()`, `formatPhone()`, `hasValidPhone()`, `formatTable()`, `log()`, `webhookLog()` are deliberately NOT declared here — `WhatsappFake` already satisfies all of them by inheriting from `Drivers\Whatsapp` (16 of them throw `Exception('Not implemented')` there already; `formatPhone`/`hasValidPhone`/`formatTable` are real pure-function logic; `log`/`webhookLog` come from the `Logging` trait). Do not redeclare any of these in this task — Tasks 2 and 3 will override the ones that need real behavior.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Testing/WhatsappFakeTest.php -v`
Expected: PASS — 21 tests (1 `instanceof` + 20 dataset rows).

- [ ] **Step 5: Run full suite + static analysis**

Run: `composer test && vendor/bin/phpstan analyse --memory-limit=512M`
Expected: both green.

- [ ] **Step 6: Commit**

```bash
git add src/Testing/WhatsappFake.php tests/Testing/WhatsappFakeTest.php
git commit -m "feat(testing): scaffold WhatsappFake satisfying WhatsappInterface

New FahriGunadi\Whatsapp\Testing\WhatsappFake class, extending
Drivers\Whatsapp to inherit its pure-function and throwing-default
methods for free. Every method WhatsappInterface requires that has
no base-class default throws Not implemented for now — real
outbound-send and message-manipulation behavior lands in the next
two tasks; webhook parsing and getMyGroups() are a future phase."
```

---

### Task 2: Outbound send builder + `assertSent`/`assertNotSent`/`assertSentCount`/`assertNothingSent`

**Files:**
- Modify: `src/Testing/WhatsappFake.php`
- Modify: `tests/Testing/WhatsappFakeTest.php`

**Interfaces:**
- Produces: `WhatsappFake::to(string $phone): static`, `::replyMessage(string $messageId, ?string $participant = null): static`, `::message(string $message): static`, `::image(string $image): static`, `::send(): Response` (all real now, no longer throwing), plus `::file(string $file): static` and `::video(string $video): static` (override the inherited base-class throw), and `::assertSent(?Closure $callback = null): void`, `::assertNotSent(?Closure $callback = null): void`, `::assertSentCount(int $count): void`, `::assertNothingSent(): void`. Also produces the private `fakeResponse(): Response` helper, which Task 3 reuses — do not redefine it there.

- [ ] **Step 1: Write the failing test**

In `tests/Testing/WhatsappFakeTest.php`, first remove these 5 rows from the `->with([...])` dataset in the `it('throws not implemented for methods not yet faked', ...)` test (they're about to get real behavior, so this test must stop asserting they throw): `'to'`, `'replyMessage'`, `'message'`, `'image'`, `'send'`. Leave `'request'` in the dataset — it stays throwing (see Global Constraints). The dataset should read:

```php
])->with([
    'request' => ['request', []],
    'webhookSender' => ['webhookSender', []],
    'webhookChat' => ['webhookChat', []],
    'webhookMessageText' => ['webhookMessageText', []],
    'webhookMessageId' => ['webhookMessageId', []],
    'webhookMessageTimestamp' => ['webhookMessageTimestamp', []],
    'webhookPushname' => ['webhookPushname', []],
    'webhookIsGroup' => ['webhookIsGroup', []],
    'webhookIsImage' => ['webhookIsImage', []],
    'webhookImageMimeType' => ['webhookImageMimeType', []],
    'webhookImage' => ['webhookImage', []],
    'webhookIsDocument' => ['webhookIsDocument', []],
    'webhookDocumentMimeType' => ['webhookDocumentMimeType', []],
    'webhookDocument' => ['webhookDocument', []],
    'getMyGroups' => ['getMyGroups', []],
]);
```

Then append these new tests to the end of the file:

```php
it('records send() and can be asserted with assertSent()', function () {
    $fake = new WhatsappFake;

    $fake->to('6289685028129@s.whatsapp.net')
        ->message('halo')
        ->replyMessage('3EB089B9D6ADD58153C561')
        ->forwarded()
        ->duration(86400)
        ->send();

    $fake->assertSent(fn (array $sent) => $sent['to'] === '6289685028129@s.whatsapp.net'
        && $sent['message'] === 'halo'
        && $sent['reply_message_id'] === '3EB089B9D6ADD58153C561'
        && $sent['forwarded'] === true
        && $sent['duration'] === 86400);
});

it('records image/file/video sends with their flags', function () {
    $fake = new WhatsappFake;

    $fake->to('6289685028129@s.whatsapp.net')->image('https://example.com/a.jpg')->viewOnce()->compress()->send();
    $fake->to('6289685028129@s.whatsapp.net')->file('https://example.com/a.pdf')->send();
    $fake->to('6289685028129@s.whatsapp.net')->video('https://example.com/a.mp4')->gifPlayback()->send();

    $fake->assertSentCount(3);
    $fake->assertSent(fn (array $sent) => $sent['image'] === 'https://example.com/a.jpg' && $sent['view_once'] === true && $sent['compress'] === true);
    $fake->assertSent(fn (array $sent) => $sent['file'] === 'https://example.com/a.pdf');
    $fake->assertSent(fn (array $sent) => $sent['video'] === 'https://example.com/a.mp4' && $sent['gif_playback'] === true);
});

it('send() returns a genuine Response with no network call', function () {
    $fake = new WhatsappFake;

    $response = $fake->to('6289685028129@s.whatsapp.net')->message('halo')->send();

    expect($response)->toBeInstanceOf(Illuminate\Http\Client\Response::class);
    expect($response->successful())->toBeTrue();
});

it('assertNotSent() passes when nothing matches', function () {
    $fake = new WhatsappFake;

    $fake->to('6289685028129@s.whatsapp.net')->message('halo')->send();

    $fake->assertNotSent(fn (array $sent) => $sent['to'] === 'someone-else@s.whatsapp.net');
});

it('assertNotSent() fails when a match exists', function () {
    $fake = new WhatsappFake;

    $fake->to('6289685028129@s.whatsapp.net')->message('halo')->send();

    $fake->assertNotSent(fn (array $sent) => $sent['to'] === '6289685028129@s.whatsapp.net');
})->throws(PHPUnit\Framework\ExpectationFailedException::class);

it('assertNothingSent() passes when send() was never called', function () {
    (new WhatsappFake)->assertNothingSent();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Testing/WhatsappFakeTest.php -v`
Expected: FAIL — uncaught `Exception: Not implemented` from the inherited/stub `to()`/`message()`/`send()` (whichever the first new test reaches).

- [ ] **Step 3: Implement the outbound builder**

In `src/Testing/WhatsappFake.php`:

Add these imports, keeping alphabetical order (Pint's `ordered_imports` fixer enforces this — insert `Closure`, `Illuminate\Support\Facades\Http`, and `PHPUnit\Framework\Assert as PHPUnit` at the correct alphabetical positions among the existing imports):

```php
use Closure;
use Exception;
use FahriGunadi\Whatsapp\Contracts\WhatsappInterface;
use FahriGunadi\Whatsapp\Drivers\Whatsapp;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Assert as PHPUnit;
```

Add these properties at the top of the class body, before the first method:

```php
    private ?string $to = null;

    private ?string $replyMessageId = null;

    private ?string $message = null;

    private ?string $image = null;

    private ?string $file = null;

    private ?string $video = null;

    private array $sent = [];
```

Replace the `to()`, `replyMessage()`, `message()`, `image()`, `request()`, `send()` stub bodies (the `request()` stub itself is unchanged — leave it throwing) with:

```php
    public function to(string $phone): static
    {
        $this->to = $phone;

        return $this;
    }

    public function replyMessage(string $messageId, ?string $participant = null): static
    {
        $this->replyMessageId = $messageId;

        return $this;
    }

    public function message(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function image(string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function file(string $file): static
    {
        $this->file = $file;

        return $this;
    }

    public function video(string $video): static
    {
        $this->video = $video;

        return $this;
    }

    public function request(): PendingRequest
    {
        throw new Exception('Not implemented');
    }

    public function send(): Response
    {
        $this->sent[] = [
            'to' => $this->to,
            'reply_message_id' => $this->replyMessageId,
            'message' => $this->message,
            'image' => $this->image,
            'file' => $this->file,
            'video' => $this->video,
            'forwarded' => $this->isForwarded,
            'duration' => $this->duration,
            'view_once' => $this->viewOnce,
            'compress' => $this->compress,
            'gif_playback' => $this->gifPlayback,
        ];

        return $this->fakeResponse();
    }

    public function assertSent(?Closure $callback = null): void
    {
        PHPUnit::assertNotEmpty(
            $this->matchingSent($callback),
            'The expected message was not sent.'
        );
    }

    public function assertNotSent(?Closure $callback = null): void
    {
        PHPUnit::assertEmpty(
            $this->matchingSent($callback),
            'A message matching the given criteria was sent.'
        );
    }

    public function assertSentCount(int $count): void
    {
        PHPUnit::assertCount($count, $this->sent);
    }

    public function assertNothingSent(): void
    {
        PHPUnit::assertEmpty($this->sent, 'Messages were sent.');
    }

    private function matchingSent(?Closure $callback): array
    {
        if (! $callback) {
            return $this->sent;
        }

        return array_filter($this->sent, $callback);
    }

    private function fakeResponse(): Response
    {
        return Http::response(['status' => 200, 'code' => 'SUCCESS'], 200);
    }
```

`$this->isForwarded`, `$this->duration`, `$this->viewOnce`, `$this->compress`, `$this->gifPlayback` are the `protected` properties already defined on the inherited `Drivers\Whatsapp` base class (set via the inherited `forwarded()`/`duration()`/`viewOnce()`/`compress()`/`gifPlayback()` fluent setters) — no new properties needed for them.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Testing/WhatsappFakeTest.php -v`
Expected: PASS — full file green.

- [ ] **Step 5: Run full suite + static analysis**

Run: `composer test && vendor/bin/phpstan analyse --memory-limit=512M`
Expected: both green.

- [ ] **Step 6: Commit**

```bash
git add src/Testing/WhatsappFake.php tests/Testing/WhatsappFakeTest.php
git commit -m "feat(testing): fake outbound send() with assertSent()

to()/replyMessage()/message()/image()/file()/video()/send() now
record a snapshot of the fluent builder state instead of throwing,
and send() returns a genuine Illuminate\Http\Client\Response built
via Http::response() with no real network call. Adds
assertSent()/assertNotSent()/assertSentCount()/assertNothingSent(),
mirroring Http::fake()'s assertion ergonomics."
```

---

### Task 3: Message manipulation (9 methods) + `assertCalled`/`assertNotCalled`/`assertCalledCount`

**Files:**
- Modify: `src/Testing/WhatsappFake.php`
- Modify: `tests/Testing/WhatsappFakeTest.php`

**Interfaces:**
- Consumes: `WhatsappFake::fakeResponse(): Response` (private, from Task 2) — reused here, do not redefine it.
- Produces: `WhatsappFake::revokeMessage(...)`, `::reactMessage(...)`, `::updateMessage(...)`, `::deleteMessage(...)`, `::readMessage(...)`, `::starMessage(...)`, `::unstarMessage(...)`, `::forwardMessage(...)`, `::downloadMessage(...)` (all override the inherited base-class throw, matching the exact signatures already declared on `WhatsappInterface`), plus `::assertCalled(string $method, ?Closure $callback = null): void`, `::assertNotCalled(string $method, ?Closure $callback = null): void`, `::assertCalledCount(string $method, int $count): void`.

- [ ] **Step 1: Write the failing test**

Append these tests to the end of `tests/Testing/WhatsappFakeTest.php` (no dataset changes needed this time — these 9 methods were never in the "throws not implemented" dataset to begin with, since Task 1 never declared them explicitly; they were inherited-throw from the base class):

```php
it('records revokeMessage() and can be asserted with assertCalled()', function () {
    $fake = new WhatsappFake;

    $fake->revokeMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');

    $fake->assertCalled('revokeMessage', fn (array $args) => $args['messageId'] === '3EB089B9D6ADD58153C561'
        && $args['phone'] === '6289685028129@s.whatsapp.net');
});

it('records reactMessage() with the emoji', function () {
    $fake = new WhatsappFake;

    $fake->reactMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net', '🙏');

    $fake->assertCalled('reactMessage', fn (array $args) => $args['emoji'] === '🙏');
});

it('records updateMessage() with the new message text', function () {
    $fake = new WhatsappFake;

    $fake->updateMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net', 'edited');

    $fake->assertCalled('updateMessage', fn (array $args) => $args['message'] === 'edited');
});

it('records deleteMessage()', function () {
    $fake = new WhatsappFake;

    $fake->deleteMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');

    $fake->assertCalled('deleteMessage');
});

it('records readMessage()', function () {
    $fake = new WhatsappFake;

    $fake->readMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');

    $fake->assertCalled('readMessage');
});

it('records starMessage() and unstarMessage()', function () {
    $fake = new WhatsappFake;

    $fake->starMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');
    $fake->unstarMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');

    $fake->assertCalled('starMessage');
    $fake->assertCalled('unstarMessage');
});

it('records forwardMessage() with the optional fields', function () {
    $fake = new WhatsappFake;

    $fake->forwardMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net', 86400, true);

    $fake->assertCalled('forwardMessage', fn (array $args) => $args['duration'] === 86400 && $args['forceReupload'] === true);
});

it('records downloadMessage()', function () {
    $fake = new WhatsappFake;

    $fake->downloadMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');

    $fake->assertCalled('downloadMessage');
});

it('message-manipulation methods return a genuine Response with no network call', function () {
    $response = (new WhatsappFake)->revokeMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');

    expect($response)->toBeInstanceOf(Illuminate\Http\Client\Response::class);
    expect($response->successful())->toBeTrue();
});

it('assertNotCalled() passes when the method was never recorded', function () {
    (new WhatsappFake)->assertNotCalled('revokeMessage');
});

it('assertNotCalled() fails when a matching call was recorded', function () {
    $fake = new WhatsappFake;

    $fake->revokeMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');

    $fake->assertNotCalled('revokeMessage');
})->throws(PHPUnit\Framework\ExpectationFailedException::class);

it('assertCalledCount() counts only the given method', function () {
    $fake = new WhatsappFake;

    $fake->revokeMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');
    $fake->revokeMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');
    $fake->reactMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net', '🙏');

    $fake->assertCalledCount('revokeMessage', 2);
    $fake->assertCalledCount('reactMessage', 1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Testing/WhatsappFakeTest.php -v`
Expected: FAIL — uncaught `Exception: Not implemented` from the inherited `revokeMessage()` base-class default (or whichever of the 9 methods the first new test reaches).

- [ ] **Step 3: Implement message manipulation**

In `src/Testing/WhatsappFake.php`, add this property alongside the existing ones from Task 2 (e.g. right after `private array $sent = [];`):

```php
    private array $calls = [];
```

Add these 9 methods plus the 3 assertion methods plus 2 private helpers anywhere in the class body (e.g. after `send()` and before `assertSent()`, or grouped together after the Task 2 assertion methods — either placement is fine, keep all message-manipulation code together):

```php
    public function revokeMessage(string $messageId, string $phone): Response
    {
        $this->recordCall('revokeMessage', compact('messageId', 'phone'));

        return $this->fakeResponse();
    }

    public function reactMessage(string $messageId, string $phone, string $emoji): Response
    {
        $this->recordCall('reactMessage', compact('messageId', 'phone', 'emoji'));

        return $this->fakeResponse();
    }

    public function updateMessage(string $messageId, string $phone, string $message): Response
    {
        $this->recordCall('updateMessage', compact('messageId', 'phone', 'message'));

        return $this->fakeResponse();
    }

    public function deleteMessage(string $messageId, string $phone): Response
    {
        $this->recordCall('deleteMessage', compact('messageId', 'phone'));

        return $this->fakeResponse();
    }

    public function readMessage(string $messageId, string $phone): Response
    {
        $this->recordCall('readMessage', compact('messageId', 'phone'));

        return $this->fakeResponse();
    }

    public function starMessage(string $messageId, string $phone): Response
    {
        $this->recordCall('starMessage', compact('messageId', 'phone'));

        return $this->fakeResponse();
    }

    public function unstarMessage(string $messageId, string $phone): Response
    {
        $this->recordCall('unstarMessage', compact('messageId', 'phone'));

        return $this->fakeResponse();
    }

    public function forwardMessage(string $messageId, string $phone, ?int $duration = null, bool $forceReupload = false): Response
    {
        $this->recordCall('forwardMessage', compact('messageId', 'phone', 'duration', 'forceReupload'));

        return $this->fakeResponse();
    }

    public function downloadMessage(string $messageId, string $phone): Response
    {
        $this->recordCall('downloadMessage', compact('messageId', 'phone'));

        return $this->fakeResponse();
    }

    public function assertCalled(string $method, ?Closure $callback = null): void
    {
        PHPUnit::assertNotEmpty(
            $this->matchingCalls($method, $callback),
            "The expected [{$method}] call was not recorded."
        );
    }

    public function assertNotCalled(string $method, ?Closure $callback = null): void
    {
        PHPUnit::assertEmpty(
            $this->matchingCalls($method, $callback),
            "A [{$method}] call matching the given criteria was recorded."
        );
    }

    public function assertCalledCount(string $method, int $count): void
    {
        PHPUnit::assertCount(
            $count,
            array_filter($this->calls, fn (array $call) => $call['method'] === $method)
        );
    }

    private function recordCall(string $method, array $arguments): void
    {
        $this->calls[] = ['method' => $method, 'arguments' => $arguments];
    }

    private function matchingCalls(string $method, ?Closure $callback): array
    {
        $matching = array_filter($this->calls, fn (array $call) => $call['method'] === $method);

        if (! $callback) {
            return $matching;
        }

        return array_filter($matching, fn (array $call) => $callback($call['arguments']));
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Testing/WhatsappFakeTest.php -v`
Expected: PASS — full file green, including all pre-existing tests.

- [ ] **Step 5: Run full suite + static analysis**

Run: `composer test && vendor/bin/phpstan analyse --memory-limit=512M`
Expected: both green.

- [ ] **Step 6: Commit**

```bash
git add src/Testing/WhatsappFake.php tests/Testing/WhatsappFakeTest.php
git commit -m "feat(testing): fake message-manipulation methods

revokeMessage()/reactMessage()/updateMessage()/deleteMessage()/
readMessage()/starMessage()/unstarMessage()/forwardMessage()/
downloadMessage() now record their method name and arguments
instead of throwing, and return a fake Response. Adds a generic
assertCalled()/assertNotCalled()/assertCalledCount(string $method, ...)
rather than 9 near-duplicate named assertions."
```

---

### Task 4: `Whatsapp::fake()` facade wiring

**Files:**
- Modify: `src/Facades/Whatsapp.php`
- Modify: `tests/FacadeTest.php`

**Interfaces:**
- Consumes: `FahriGunadi\Whatsapp\Testing\WhatsappFake` (from Tasks 1-3).
- Produces: `Facades\Whatsapp::fake(): WhatsappFake` — swaps the container's `WhatsappInterface::class` binding to a fresh `WhatsappFake` instance and returns it.

- [ ] **Step 1: Write the failing test**

Append to `tests/FacadeTest.php` (current content ends with the `it('resolves the root Whatsapp facade to the bound WhatsappInterface', ...)` test):

```php
it('fake() swaps the container binding to a WhatsappFake instance', function () {
    $fake = WhatsappFacade::fake();

    expect($fake)->toBeInstanceOf(FahriGunadi\Whatsapp\Testing\WhatsappFake::class);
    expect(app(WhatsappInterface::class))->toBe($fake);
    expect(whatsapp())->toBe($fake);
    expect(WhatsappFacade::getFacadeRoot())->toBe($fake);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/FacadeTest.php -v`
Expected: FAIL — `Call to undefined method FahriGunadi\Whatsapp\Facades\Whatsapp::fake()`.

- [ ] **Step 3: Add `fake()` to the facade**

In `src/Facades/Whatsapp.php`, the current file is:

```php
<?php

namespace FahriGunadi\Whatsapp\Facades;

use FahriGunadi\Whatsapp\Contracts\WhatsappInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @see WhatsappInterface
 */
class Whatsapp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WhatsappInterface::class;
    }
}
```

Replace it with:

```php
<?php

namespace FahriGunadi\Whatsapp\Facades;

use FahriGunadi\Whatsapp\Contracts\WhatsappInterface;
use FahriGunadi\Whatsapp\Testing\WhatsappFake;
use Illuminate\Support\Facades\Facade;

/**
 * @see WhatsappInterface
 */
class Whatsapp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WhatsappInterface::class;
    }

    public static function fake(): WhatsappFake
    {
        $fake = new WhatsappFake;

        static::swap($fake);

        return $fake;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/FacadeTest.php -v`
Expected: PASS — all 4 tests in the file.

- [ ] **Step 5: Run full suite + static analysis**

Run: `composer test && vendor/bin/phpstan analyse --memory-limit=512M`
Expected: both green.

- [ ] **Step 6: Commit**

```bash
git add src/Facades/Whatsapp.php tests/FacadeTest.php
git commit -m "feat(testing): add Whatsapp::fake() facade method

Swaps the WhatsappInterface container binding to a fresh
WhatsappFake via Laravel's Facade::swap() — the same mechanism
Mail::fake()/Queue::fake()/Notification::fake() use. Because
whatsapp() also resolves WhatsappInterface from the container,
calling Whatsapp::fake() makes both the facade and the helper
return the same fake instance."
```

---

## Post-plan checklist (manual, not a task)

- Re-read `docs/superpowers/specs/2026-07-20-whatsapp-fake-outbound-design.md` "Out of scope" section and confirm no webhook/`getMyGroups` real behavior was built.
- A future plan should cover phase 2 (webhook/inbound parsing fake) and phase 3 (`getMyGroups()`/remaining utility), following this same skeleton-then-build-out pattern.
- Consider a README section documenting `Whatsapp::fake()` usage once this ships — not part of this plan.
