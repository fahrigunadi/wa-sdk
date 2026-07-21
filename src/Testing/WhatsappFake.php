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
