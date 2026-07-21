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
        $psr7Response = new Psr7Response(200, [], json_encode(['status' => 200, 'code' => 'SUCCESS']));

        return new Response($psr7Response);
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
