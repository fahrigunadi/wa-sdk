<?php

declare(strict_types=1);

namespace FahriGunadi\WhatsApp\Drivers;

use Exception;
use FahriGunadi\Whatsapp\Contracts\WhatsappInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class AldinokemalWhatsapp extends Whatsapp implements WhatsappInterface
{
    private ?string $to = null;

    private ?string $replyMessageId = null;

    private ?string $message = null;

    private ?string $image = null;

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

    public function request(): PendingRequest
    {
        $username = config('whatsapp.username');
        $password = config('whatsapp.password');
        $baseUrl = config('whatsapp.base_url');

        throw_unless($username, new Exception('Config whatsapp.username must be set'));

        throw_unless($password, new Exception('Config whatsapp.password must be set'));

        throw_unless($baseUrl, new Exception('Config whatsapp.base_url must be set'));

        return Http::withBasicAuth($username, $password)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Request-With' => 'XMLHttpRequest',
                'User-Agent' => 'Fahri/1.0',
            ])
            ->baseUrl($baseUrl);
    }

    public function send(): Response
    {
        $this->validateData();

        if ($this->image) {
            return $this->request()->post('/send/image', [
                'phone' => $this->to,
                'caption' => $this->message,
                'reply_message_id' => $this->replyMessageId,
                'image_url' => str($this->image)->isUrl() ? $this->image : Storage::url($this->image),
            ]);
        }

        return $this->request()->post('/send/message', [
            'phone' => $this->to,
            'reply_message_id' => $this->replyMessageId,
            'message' => $this->message,
        ]);
    }

    public function webhookSender(): string
    {
        return str(request()->from)->before(' ')->toString();
    }

    public function webhookChat(): string
    {
        return str(request()->from)->afterLast(' ')->toString();
    }

    public function webhookMessageText(): ?string
    {
        if ($this->webhookIsImage()) {
            return request()->image['caption'] ?? null;
        }

        if ($this->webhookIsDocument()) {
            return request()->document['caption'] ?? null;
        }

        return request()->message['text'] ?? null;
    }

    public function webhookMessageId(): ?string
    {
        return request()->message['id'] ?? null;
    }

    public function webhookMessageTimestamp(): ?string
    {
        return request()->timestamp;
    }

    public function webhookPushname(): ?string
    {
        return request()->pushname;
    }

    public function webhookIsGroup(): bool
    {
        $chat = $this->webhookChat();

        return (bool) preg_match('/^[\w\d\-]+@g\.us$/', $chat);
    }

    public function webhookIsImage(): bool
    {
        return request()->has('image');
    }

    public function webhookImageMimeType(): ?string
    {
        $mimeType = request()->image['mime_type'] ?? null;

        if (! $mimeType) {
            return null;
        }

        return str_replace('\/', '/', $mimeType);
    }

    public function webhookImage(): ?string
    {
        $mediaPath = request()->image['media_path'] ?? null;

        if (! $mediaPath) {
            return null;
        }

        $mediaPath = str_replace('\/', '/', $mediaPath);

        return sprintf('%s/%s', config('whatsapp.base_url'), $mediaPath);
    }

    public function webhookIsDocument(): bool
    {
        return request()->has('document');
    }

    public function webhookDocumentMimeType(): ?string
    {
        $mimeType = request()->document['mime_type'] ?? null;

        if (! $mimeType) {
            return null;
        }

        return str_replace('\/', '/', $mimeType);
    }

    public function webhookDocument(): ?string
    {
        $mediaPath = request()->document['media_path'] ?? null;

        if (! $mediaPath) {
            return null;
        }

        $mediaPath = str_replace('\/', '/', $mediaPath);

        return sprintf('%s/%s', config('whatsapp.base_url'), $mediaPath);
    }

    protected function validateData()
    {
        throw_unless($this->to, new Exception('Target must be set'));

        throw_if(! $this->message && ! $this->image, new Exception('Message or Image must be set'));
    }

    public function getMyGroups(): Collection
    {
        $response = $this->request()->get('/user/my/groups');

        return collect($response->collect('results')->get('data'));
    }
}
