# WhatsappFake (Outbound Send + Message Manipulation) — Design

## Context

`WhatsappInterface` has grown to 41 methods across four capability groups (outbound send builder, message manipulation, webhook/inbound parsing, utility). Library consumers currently have no way to test their own application code against this package without either hitting a real WhatsApp gateway or manually mocking the container binding themselves. This design adds a first-class fake, following the same pattern as Laravel's own `Mail::fake()` / `Queue::fake()` / `Notification::fake()`: a dedicated class implementing the full contract, swapped into the container via the facade, offering assertion helpers, and making zero real network calls.

Given the interface's size, this is being built in phases. This design covers phase 1: the **outbound send builder** (`to`, `replyMessage`, `message`, `image`, `file`, `video`, `forwarded`, `duration`, `viewOnce`, `compress`, `gifPlayback`, `request`, `send`) and **message manipulation** (`revokeMessage`, `reactMessage`, `updateMessage`, `deleteMessage`, `readMessage`, `starMessage`, `unstarMessage`, `forwardMessage`, `downloadMessage`) — 22 of the 41 methods, and the two groups a consumer is overwhelmingly most likely to test against ("did my app send the right message", "did my app revoke/react-to the right message"). Webhook/inbound parsing (13 methods) and remaining utility (`getMyGroups`, `request`) are deferred to a later phase.

### Prerequisite fix (already done, separate commit)

While designing where `WhatsappFake::fake()` should be exposed, found and fixed a pre-existing bug: `Facades\Whatsapp::getFacadeAccessor()` — the facade registered as the `Whatsapp` alias in `composer.json`, the one a consuming app actually gets — pointed at `FahriGunadi\Whatsapp\Whatsapp::class` (a second, separate facade class) instead of `WhatsappInterface::class`. Since nothing binds that second class in the container, the container auto-built a bare instance with no usable methods, so every call through the registered alias threw `Call to undefined method`. Fixed in commit `a2fb031` by pointing the accessor straight at `WhatsappInterface::class`, matching how `FahriGunadi\Whatsapp\Whatsapp` (left in place, non-breaking, still used by the README's direct-class-reference usage) already did it. This fix is a prerequisite for this design because `Whatsapp::fake()` needs to live on a facade that actually works.

## Decisions from brainstorming

- **Approach:** a dedicated `WhatsappFake` class implementing `WhatsappInterface` in full, swapped into the container via `Facade::swap()` — matching `Mail::fake()`/`Queue::fake()`/`Notification::fake()`, not `Http::fake()`'s different (intercept-at-the-HTTP-layer) mechanism. Chosen over a thin wrapper around the existing `Http::fake()` because it needs zero config (no dummy `username`/`password`/`base_url`), doesn't depend on which underlying driver (`aldinokemal`/`aldinokemal_v8`/`wuzapi`) the consuming app has configured, and never touches the network layer at all.
- **Namespace/location:** `FahriGunadi\Whatsapp\Testing\WhatsappFake`, a new `src/Testing/` directory — mirrors Laravel's own `Illuminate\Support\Testing\Fakes\*` convention, and keeps `src/Drivers/` reserved for real gateway integrations only.
- **Class relationship:** `WhatsappFake extends Drivers\Whatsapp implements WhatsappInterface` — inherits `formatPhone()`, `hasValidPhone()`, `formatTable()`, `isValidBase64()`, `log()`, `webhookLog()` for free (pure functions / harmless logging, no I/O to fake), plus the existing throwing defaults for methods this phase doesn't touch yet.
- **Scope discipline:** PHP cannot partially implement an interface. Every one of the 41 `WhatsappInterface` methods must exist on `WhatsappFake` from this first commit. The 13 `webhook*()` methods, `getMyGroups()`, and `request()` (none of which have base-class defaults to inherit) get explicit `throw new Exception('Not implemented')` bodies in phase 1 — identical to the established codebase convention already used for out-of-scope driver capabilities — to be built out in a later phase.
- **Fake responses:** every faked method returns a real `Illuminate\Http\Client\Response`, constructed via Laravel's `Http::response($body, $status)` factory helper (the same one used inside `Http::fake([...])` closures) — a genuine `Response` object with zero network I/O.
- **Assertion API shape:** `send()`-family gets its own named assertions (`assertSent`, `assertNotSent`, `assertSentCount`, `assertNothingSent`) since it's the primary use case. The 9 message-manipulation methods share one generic mechanism (`assertCalled(string $method, ?Closure $callback = null)`, `assertNotCalled`, `assertCalledCount`) rather than 9 near-duplicate named assertions — avoids boilerplate while staying fully typed and inspectable.

## Architecture

### `src/Testing/WhatsappFake.php`

```php
namespace FahriGunadi\Whatsapp\Testing;

class WhatsappFake extends Drivers\Whatsapp implements WhatsappInterface
{
    // Outbound builder state (mirrors AldinokemalV8Whatsapp's private props)
    private ?string $to = null;
    private ?string $replyMessageId = null;
    private ?string $message = null;
    private ?string $image = null;
    private ?string $file = null;
    private ?string $video = null;

    // Recorded history
    private array $sent = [];   // each entry: full snapshot of builder state at send()-time
    private array $calls = [];  // each entry: ['method' => string, 'arguments' => array]

    // --- Group A: outbound builder ---
    public function to(string $phone): static { ... }
    public function replyMessage(string $messageId, ?string $participant = null): static { ... }
    public function message(string $message): static { ... }
    public function image(string $image): static { ... }
    public function file(string $file): static { ... }   // overrides base class's throw
    public function video(string $video): static { ... }  // overrides base class's throw

    public function send(): Response
    {
        $this->sent[] = [/* to, reply_message_id, message, image, file, video,
                            forwarded, duration, view_once, compress, gif_playback */];
        return Http::response(['status' => 200, 'code' => 'SUCCESS'], 200);
    }

    public function assertSent(?Closure $callback = null): void { ... }
    public function assertNotSent(?Closure $callback = null): void { ... }
    public function assertSentCount(int $count): void { ... }
    public function assertNothingSent(): void { ... }

    // --- Group B: message manipulation (9 methods, uniform recording) ---
    public function revokeMessage(string $messageId, string $phone): Response
    {
        $this->recordCall('revokeMessage', compact('messageId', 'phone'));
        return $this->fakeResponse();
    }
    // ... reactMessage, updateMessage, deleteMessage, readMessage,
    //     starMessage, unstarMessage, forwardMessage, downloadMessage
    //     — same shape, each records its own method name + args.

    public function assertCalled(string $method, ?Closure $callback = null): void { ... }
    public function assertNotCalled(string $method, ?Closure $callback = null): void { ... }
    public function assertCalledCount(string $method, int $count): void { ... }

    private function recordCall(string $method, array $arguments): void { ... }
    private function fakeResponse(): Response { return Http::response(['status' => 200, 'code' => 'SUCCESS'], 200); }

    // --- Group C/D: deferred, explicit placeholders ---
    public function webhookSender(): string { throw new Exception('Not implemented'); }
    // ... all 13 webhook*() methods, same body ...
    public function getMyGroups(): Collection { throw new Exception('Not implemented'); }
    public function request(): PendingRequest { throw new Exception('Not implemented'); }
}
```

`forwarded()`, `duration()`, `viewOnce()`, `compress()`, `gifPlayback()` are inherited unchanged from `Drivers\Whatsapp` — they only set protected properties, no network, already fake-safe. `file()`/`video()` are overridden here (the base class throws for those by default; the fake needs them to actually store state like a real driver's setter).

### `src/Facades/Whatsapp.php`

```php
public static function fake(): WhatsappFake
{
    $fake = new WhatsappFake();
    static::swap($fake);
    return $fake;
}
```

`Facade::swap()` (Laravel core) rebinds the container entry for the facade's accessor (`WhatsappInterface::class`, now that the prerequisite fix is in) and sets the facade's resolved-instance cache. Because `src/helpers.php`'s `whatsapp()` helper also resolves `WhatsappInterface::class` from the container, calling `Whatsapp::fake()` makes both the facade and the `whatsapp()` helper return the same fake instance — no extra wiring needed.

## Error handling

- Calling any of the 19 not-yet-faked methods (13 webhook + `getMyGroups` + `request`, minus the 5 already covered by inherited base-class behavior for pure utilities) throws `Exception('Not implemented')`, identical to how real drivers signal an unbuilt capability elsewhere in this codebase.
- `assertSent()`/`assertCalled()`/etc. use Pest/PHPUnit-style assertion failures (`throw new ExpectationFailedException(...)` or PHPUnit's `Assert::assertTrue(...)` with a descriptive message) when the expectation isn't met — not silent `false` returns — matching how `Http::assertSent()` behaves.

## Testing

New Pest file `tests/Testing/WhatsappFakeTest.php`:
- `send()` for each of text/image/file/video, asserting every recorded field is correct.
- `assertSent()` / `assertNotSent()` / `assertSentCount()` / `assertNothingSent()` — positive and negative case for each.
- Each of the 9 message-manipulation methods records correctly, verified via `assertCalled()`; plus `assertNotCalled()` / `assertCalledCount()` generic-mechanism coverage.
- `Whatsapp::fake()` actually swaps the container: `app(WhatsappInterface::class)` and `whatsapp()` both return the same `WhatsappFake` instance after calling it.
- The 13 webhook methods, `getMyGroups()`, and `request()` all throw `Exception('Not implemented')`.
- No `Http::fake()` needed anywhere in this test file — `WhatsappFake` never touches the HTTP client, so there's nothing to intercept.

## Out of scope (this phase)

- Webhook/inbound parsing methods (13) — real behavior deferred to a later phase; only the required throwing placeholder ships now.
- `getMyGroups()` — same treatment.
- Configurable/custom fake response bodies (a fixed default success shape ships; per-call customization is a possible future addition, not built now).
- Any change to real driver classes (`AldinokemalWhatsapp`, `AldinokemalV8Whatsapp`, `WuzapiWhatsapp`) or the abstract `Drivers\Whatsapp` base class beyond what's already fixed in the prerequisite facade commit.
