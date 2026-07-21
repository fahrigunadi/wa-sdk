<?php

declare(strict_types=1);

namespace FahriGunadi\Whatsapp\Testing;

use Closure;
use Exception;
use FahriGunadi\Whatsapp\Contracts\WhatsappInterface;
use FahriGunadi\Whatsapp\Drivers\Whatsapp;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert as PHPUnit;

class WhatsappFake extends Whatsapp implements WhatsappInterface
{
    private ?string $to = null;

    private ?string $replyMessageId = null;

    private ?string $message = null;

    private ?string $image = null;

    private ?string $file = null;

    private ?string $video = null;

    private array $sent = [];

    private array $calls = [];

    private array $webhookPayload = [];

    private ?Collection $groups = null;

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

    private function matchingSent(?Closure $callback): array
    {
        if (! $callback) {
            return $this->sent;
        }

        return array_filter($this->sent, $callback);
    }

    private function fakeResponse(): Response
    {
        // Illuminate\Support\Facades\Http::response() returns a
        // GuzzleHttp\Promise\PromiseInterface, not a Response, so it can't
        // be used here — build the PSR-7 response directly instead.
        $psr7Response = new Psr7Response(200, [], json_encode(['status' => 200, 'code' => 'SUCCESS']));

        return new Response($psr7Response);
    }

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
}
