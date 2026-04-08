<?php

declare(strict_types=1);

namespace FahriGunadi\WhatsApp\Drivers;

use Exception;
use FahriGunadi\Whatsapp\Contracts\WhatsappDeviceInterface;
use FahriGunadi\Whatsapp\Contracts\WhatsappInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class AldinokemalV8Whatsapp extends Whatsapp implements WhatsappDeviceInterface, WhatsappInterface
{
    private ?string $device = null;

    private ?string $to = null;

    private ?string $replyMessageId = null;

    private ?string $message = null;

    private ?string $image = null;

    public function device(string $device): static
    {
        $this->device = $device;

        return $this;
    }

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
            ->when($this->device, fn (PendingRequest $request) => $request->withHeader('X-Device-Id', $this->device))
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
        return str(request()->json('payload.from'))->before(' ')->toString();
    }

    public function webhookChat(): string
    {
        return str(request()->json('payload.chat_id'))->afterLast(' ')->toString();
    }

    public function webhookMessageText(): ?string
    {
        if ($this->webhookIsImage()) {
            return request()->json('payload.caption');
        }

        if ($this->webhookIsDocument()) {
            return request()->json('payload.caption');
        }

        return request()->json('payload.body');
    }

    public function webhookMessageId(): ?string
    {
        return request()->json('payload.id');
    }

    public function webhookMessageTimestamp(): ?string
    {
        return request()->json('payload.timestamp');
    }

    public function webhookPushname(): ?string
    {
        return request()->json('payload.from_name');
    }

    public function webhookIsGroup(): bool
    {
        $chat = $this->webhookChat();

        return (bool) preg_match('/^[\w\d\-]+@g\.us$/', $chat);
    }

    public function webhookIsImage(): bool
    {
        return request()->has('payload.image');
    }

    public function webhookImageMimeType(): ?string
    {
        throw new Exception('Not implemented');
    }

    public function webhookImage(): ?string
    {
        $mediaPath = request()->json('payload.image');

        if (! $mediaPath) {
            return null;
        }

        $mediaPath = str_replace('\/', '/', $mediaPath);

        return sprintf('%s/%s', config('whatsapp.base_url'), $mediaPath);
    }

    public function webhookIsDocument(): bool
    {
        return request()->has('payload.document');
    }

    public function webhookDocumentMimeType(): ?string
    {
        throw new Exception('Not implemented');
    }

    public function webhookDocument(): ?string
    {
        $mediaPath = request()->json('payload.document');

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
