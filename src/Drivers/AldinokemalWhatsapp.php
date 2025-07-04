<?php

declare(strict_types=1);

namespace FahriGunadi\WhatsApp\Drivers;

use Exception;
use FahriGunadi\Whatsapp\Contracts\WhatsappInterface;
use FahriGunadi\Whatsapp\Traits\Logging;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class AldinokemalWhatsapp implements WhatsappInterface
{
    use Logging;

    private ?string $to = null;

    private ?string $message = null;

    private ?string $image = null;

    public function to(string $phone): static
    {
        $this->to = $phone;

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
        if ($this->image) {
            return $this->request()->post('/send/image', [
                'phone' => $this->to,
                'caption' => $this->message,
                'image_url' => str($this->image)->isUrl() ? $this->image : Storage::url($this->image),
            ]);
        }

        return $this->request()->post('/send/message', [
            'phone' => $this->to,
            'message' => $this->message,
        ]);
    }

    public function formatTable(Collection $items, array $columns): string
    {
        $maxLengths = [];
        foreach ($columns as $field => $header) {
            $maxFromValues = $items->max(fn ($item) => strlen((string) data_get($item, $field)));
            $maxLengths[$field] = max(strlen($header), $maxFromValues);
        }

        $lines = [];

        $headerLine = '';
        foreach ($columns as $field => $header) {
            $headerLine .= str_pad($header, $maxLengths[$field]).' | ';
        }
        $lines[] = rtrim($headerLine, ' | ');

        $separatorLine = '';
        foreach ($columns as $field => $_) {
            $separatorLine .= str_repeat('-', $maxLengths[$field]).'-|-';
        }
        $lines[] = rtrim($separatorLine, '-|-');

        foreach ($items as $item) {
            $rowLine = '';
            foreach ($columns as $field => $_) {
                $value = (string) data_get($item, $field);
                $rowLine .= str_pad($value, $maxLengths[$field]).' | ';
            }
            $lines[] = rtrim($rowLine, ' | ');
        }

        return "```\n".implode("\n", $lines)."\n```";
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

    public function formatPhone(string $phone): string
    {
        throw_if(! $phone, new InvalidArgumentException('Phone number must be set'));

        $phone = str($phone)
            ->replace([' ', '-', '(', ')', '+'], '')
            ->trim()
            ->whenContains('@s.whatsapp.net', fn ($p) => $p->before('@s.whatsapp.net'))
            ->whenStartsWith('08', fn ($p) => $p->substr(1)->prepend('62'));

        return $phone->toString();
    }

    public function hasValidPhone(string $phone): bool
    {
        return (bool) preg_match('/^(\+62|62|0)8[1-9][0-9]{6,9}$/', $phone);
    }

    protected function validateData()
    {
        throw_if(! $this->message && ! $this->image, new Exception('Message or Image must be set'));

        throw_unless($this->to, new Exception('Target must be set'));

        throw_unless($this->hasValidPhone($this->to), new Exception('Target is invalid'));
    }
}
