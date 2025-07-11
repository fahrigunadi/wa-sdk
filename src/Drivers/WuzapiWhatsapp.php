<?php

declare(strict_types=1);

namespace FahriGunadi\WhatsApp\Drivers;

use Exception;
use FahriGunadi\Whatsapp\Contracts\WhatsappInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class WuzapiWhatsapp extends Whatsapp implements WhatsappInterface
{
    private ?string $to = null;

    private ?string $replyStanzaId = null;

    private ?string $replyParticipant = null;

    private ?string $message = null;

    private ?string $image = null;

    public function to(string $phone): static
    {
        $this->to = $phone;

        return $this;
    }

    public function replyMessage(string $messageId, ?string $participant = null): static
    {
        throw_unless($messageId, new InvalidArgumentException('Message Id is required'));

        throw_unless($participant, new InvalidArgumentException('Participant is required'));

        $this->replyStanzaId = $messageId;
        $this->replyParticipant = $participant;

        return $this;
    }

    public function message(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function image(string $image): static
    {
        throw_unless($this->isValidBase64($image), new InvalidArgumentException('Invalid base64 image'));

        $this->image = $image;

        return $this;
    }

    public function request(): PendingRequest
    {
        $password = config('whatsapp.password');
        $baseUrl = config('whatsapp.base_url');

        throw_unless($password, new Exception('Config whatsapp.password must be set'));

        throw_unless($baseUrl, new Exception('Config whatsapp.base_url must be set'));

        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Request-With' => 'XMLHttpRequest',
            'User-Agent' => 'Fahri/1.0',
            'Token' => $password,
        ])
            ->baseUrl($baseUrl);
    }

    public function send(): Response
    {
        $this->validateData();

        $data = [
            'Phone' => $this->to,
        ];

        if ($this->replyStanzaId && $this->replyParticipant) {
            $data['ContextInfo'] = [
                'StanzaId' => $this->replyStanzaId,
                'Participant' => $this->replyParticipant,
            ];
        }

        if ($this->image) {
            return $this->request()->post('/chat/send/image', [
                ...$data,
                'Image' => $this->image,
                'Caption' => $this->message,
            ]);
        }

        return $this->request()->post('/chat/send/message', [
            ...$data,
            'Body' => $this->message,
        ]);
    }

    public function webhookSender(): string
    {
        return Arr::get(request()->all(), 'event.Info.Sender');
    }

    public function webhookChat(): string
    {
        return Arr::get(request()->all(), 'event.Info.Chat');
    }

    public function webhookMessageText(): ?string
    {
        if ($this->webhookIsImage()) {
            return request()->image['caption'] ?? null;
        }

        if ($this->webhookIsDocument()) {
            return request()->document['caption'] ?? null;
        }

        $data = request()->all();

        return Arr::get($data, 'event.RawMessage.extendedTextMessage.text')
            ?? (Arr::get($data, 'event.RawMessage.conversation')
                ?? Arr::get($data, 'event.Message.extendedTextMessage.text'));
    }

    public function webhookMessageId(): ?string
    {
        return Arr::get(request()->all(), 'event.Info.ID');
    }

    public function webhookMessageTimestamp(): ?string
    {
        return Arr::get(request()->all(), 'event.Info.Timestamp');
    }

    public function webhookPushname(): ?string
    {
        return Arr::get(request()->all(), 'event.Info.PushName');
    }

    public function webhookIsGroup(): bool
    {
        return (bool) Arr::get(request()->all(), 'event.Info.IsGroup');
    }

    public function webhookIsImage(): bool
    {
        $data = request()->all();

        return Arr::get($data, 'event.Info.Type') === 'media' &&
            Arr::get($data, 'event.Info.MediaType') === 'image';
    }

    public function webhookImageMimeType(): ?string
    {
        $mimeType = Arr::get(request()->all(), 'event.RawMessage.imageMessage.mimetype');

        if (! $mimeType) {
            return null;
        }

        return stripslashes($mimeType);
    }

    public function webhookImage(): ?string
    {
        $imageMessage = Arr::get(request()->all(), 'event.RawMessage.imageMessage');

        if (! $imageMessage) {
            return null;
        }

        $url = Arr::get($imageMessage, 'URL');
        $directPath = Arr::get($imageMessage, 'directPath');
        $mediaKey = Arr::get($imageMessage, 'mediaKey');
        $mimetype = Arr::get($imageMessage, 'mimetype');
        $fileEncSHA256 = Arr::get($imageMessage, 'fileEncSHA256');
        $fileSHA256 = Arr::get($imageMessage, 'fileSHA256');
        $fileLength = Arr::get($imageMessage, 'fileSHA256');

        if (
            ! $url ||
            ! $directPath ||
            ! $mediaKey ||
            ! $mimetype ||
            ! $fileEncSHA256 ||
            ! $fileSHA256 ||
            ! $fileLength
        ) {
            return null;
        }

        $res = $this->request()->post('/chat/downloadimage', [
            'Url' => stripslashes($url),
            'DirectPath' => stripslashes($directPath),
            'MediaKey' => stripslashes($mediaKey),
            'Mimetype' => stripslashes($mimetype),
            'FileEncSHA256' => stripslashes($fileEncSHA256),
            'FileSHA256' => stripslashes($fileSHA256),
            'FileLength' => stripslashes($fileLength),
        ]);

        if ($res->failed()) {
            return null;
        }

        return $res->json('data')['Data'] ?? null;
    }

    public function webhookIsDocument(): bool
    {
        $data = request()->all();

        return Arr::get($data, 'event.Info.Type') === 'media' &&
            Arr::get($data, 'event.Info.MediaType') === 'document';
    }

    public function webhookDocumentMimeType(): ?string
    {
        $mimeType = Arr::get(request()->all(), 'event.RawMessage.documentMessage.mimetype');

        if (! $mimeType) {
            return null;
        }

        return stripslashes($mimeType);
    }

    public function webhookDocument(): ?string
    {
        $documentMessage = Arr::get(request()->all(), 'event.RawMessage.documentMessage');

        if (! $documentMessage) {
            return null;
        }

        $url = Arr::get($documentMessage, 'URL');
        $directPath = Arr::get($documentMessage, 'directPath');
        $mediaKey = Arr::get($documentMessage, 'mediaKey');
        $mimetype = Arr::get($documentMessage, 'mimetype');
        $fileEncSHA256 = Arr::get($documentMessage, 'fileEncSHA256');
        $fileSHA256 = Arr::get($documentMessage, 'fileSHA256');
        $fileLength = Arr::get($documentMessage, 'fileSHA256');

        if (
            ! $url ||
            ! $directPath ||
            ! $mediaKey ||
            ! $mimetype ||
            ! $fileEncSHA256 ||
            ! $fileSHA256 ||
            ! $fileLength
        ) {
            return null;
        }

        $res = $this->request()->post('/chat/downloadimage', [
            'Url' => stripslashes($url),
            'DirectPath' => stripslashes($directPath),
            'MediaKey' => stripslashes($mediaKey),
            'Mimetype' => stripslashes($mimetype),
            'FileEncSHA256' => stripslashes($fileEncSHA256),
            'FileSHA256' => stripslashes($fileSHA256),
            'FileLength' => stripslashes($fileLength),
        ]);

        if ($res->failed()) {
            return null;
        }

        return $res->json('data')['Data'] ?? null;
    }

    protected function validateData()
    {
        throw_unless($this->to, new Exception('Target must be set'));

        throw_if(! $this->message && ! $this->image, new Exception('Message or Image must be set'));
    }

    public function getMyGroups(): Collection
    {
        $response = $this->request()->get('/group/list');

        return collect($response->collect('data')->get('Groups'));
    }
}
